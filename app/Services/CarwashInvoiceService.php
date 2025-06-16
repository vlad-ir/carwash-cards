<?php

namespace App\Services;

use App\Models\CarwashClient;
use App\Models\CarwashBonusCard;
use App\Models\CarwashBonusCardStat;
use App\Models\CarwashInvoice;
use App\Mail\CarwashInvoiceMail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class CarwashInvoiceService
{
    /**
     * Creates and sends an invoice for a given client for the previous month.
     *
     * @param CarwashClient $client
     * @param Carbon $periodDate Date based on which the reporting period (previous month) is determined.
     * @return bool True on success, false on failure.
     */
    public function createAndSendInvoiceForClient(CarwashClient $client, Carbon $periodDate): bool
    {
        Log::info("Starting invoice generation for client ID: {$client->id} for period based on {$periodDate->toDateString()}");

        DB::beginTransaction(); // Start a database transaction

        try {
            // 1. Define Reporting Period (previous month from $periodDate)
            $periodStart = $periodDate->copy()->subMonthNoOverflow()->startOfMonth();
            $periodEnd = $periodDate->copy()->subMonthNoOverflow()->endOfMonth();
            Log::info("Reporting period for client ID {$client->id}: {$periodStart->toDateString()} to {$periodEnd->toDateString()}");

            // 2. Load Client Relations & Get Card Counts
            $client->load('bonusCards');
            $totalCardsCount = $client->bonusCards->count();
            $activeCardsCollection = $client->bonusCards->where('status', 'active');
            $activeCardsCount = $activeCardsCollection->count();
            $blockedCardsCount = $client->bonusCards->where('status', 'blocked')->count();

            Log::info("Client ID {$client->id} card counts: Total={$totalCardsCount}, Active={$activeCardsCount}, Blocked={$blockedCardsCount}");

            // 3. Gather Card Usage Statistics & Calculate Amounts
            $cardStatsForXls = [];
            $totalAmountWithoutVat = 0.0;

            foreach ($activeCardsCollection as $card) {
                $statsForCard = CarwashBonusCardStat::where('card_id', $card->id)
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->get();

                $totalDurationSecondsForCard = $statsForCard->sum('duration_seconds');

                // The generateInvoiceXls method will calculate minutes and amount per card.
                // We only add cards to XLS details if they had usage.
                if ($totalDurationSecondsForCard > 0) {
                    $cardStatsForXls[] = (object) [ // Cast to object to match expected structure in generateInvoiceXls
                        'bonusCard' => $card,
                        'duration_seconds' => $totalDurationSecondsForCard,
                    ];
                    // Temporary calculation for totalAmountWithoutVat, generateInvoiceXls will do its own per-card calc
                    $minutes = (int) ceil($totalDurationSecondsForCard / 60.0);
                    if ($totalDurationSecondsForCard > 0 && $minutes == 0) {
                        $minutes = 1;
                    }
                    $totalAmountWithoutVat += $minutes * $card->rate_per_minute;
                }
            }
            Log::info("Collected usage statistics for {$client->id}. Total amount without VAT (preliminary): {$totalAmountWithoutVat}");

            // 4. Calculate VAT
            $calculateVat = config('invoice.calculate_vat', false);
            $vatPercentage = config('invoice.vat_percentage', 0.20);
            $vatAmount = 0.0;

            if ($calculateVat) {
                $vatAmount = $totalAmountWithoutVat * $vatPercentage;
            }
            $totalAmountWithVat = $totalAmountWithoutVat + $vatAmount;
            Log::info("Client ID {$client->id}: Calculate VAT={$calculateVat}, VAT Amount={$vatAmount}, Total With VAT={$totalAmountWithVat}");

            // 5. Call generateInvoiceXls
            // Note: generateInvoiceXls will re-calculate amounts per card and totals based on its logic.
            // The $totalAmountWithoutVat, $vatAmount, $totalAmountWithVat passed here are the overall totals.
            $xlsFilePath = $this->generateInvoiceXls(
                $client,
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
                $cardStatsForXls, // This contains only cards with usage
                $totalAmountWithoutVat,
                $vatAmount,
                $totalAmountWithVat,
                $totalCardsCount,      // Pass overall card counts
                $activeCardsCount,
                $blockedCardsCount
            );
            Log::info("Client ID {$client->id}: XLS invoice generated at {$xlsFilePath}");

            // 6. Save Invoice to Database
            $invoice = CarwashInvoice::create([
                'client_id' => $client->id,
                'amount' => $totalAmountWithVat, // Save final amount with VAT
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'total_cards_count' => $totalCardsCount,
                'active_cards_count' => $activeCardsCount,
                'blocked_cards_count' => $blockedCardsCount,
                'file_path' => $xlsFilePath, // Relative path if storage_path('app/') is base
                'sent_at' => Carbon::now(),
            ]);
            Log::info("Client ID {$client->id}: Invoice ID {$invoice->id} saved to database.");

            // 7. Send Email
            Mail::to($client->email)->send(new CarwashInvoiceMail($client, $invoice, $xlsFilePath));
            Log::info("Client ID {$client->id}: Invoice email sent to {$client->email}.");

            DB::commit(); // Commit transaction
            Log::info("Successfully generated and sent invoice for client ID: {$client->id}");
            return true;

        } catch (Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            Log::error("Error during invoice generation for client ID {$client->id}: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Generates an XLS invoice file from a template.
     * (Updated to accept overall card counts for header display)
     *
     * @param CarwashClient $client
     * @param string $periodStart
     * @param string $periodEnd
     * @param array $cardStatsForDetails Array of objects, each with 'bonusCard' and 'duration_seconds' FOR USED CARDS
     * @param float $overallTotalAmountWithoutVat
     * @param ?float $overallVatAmount
     * @param float $overallTotalAmountWithVat
     * @param int $totalCardsCountOverall
     * @param int $activeCardsCountOverall
     * @param int $blockedCardsCountOverall
     * @return string The path to the saved XLS file.
     * @throws Exception
     */
    public function generateInvoiceXls(
        CarwashClient $client,
        string $periodStart, // YYYY-MM-DD
        string $periodEnd,   // YYYY-MM-DD
        array $cardStatsForDetails, // Only cards with usage
        float $overallTotalAmountWithoutVat,
        ?float $overallVatAmount,
        float $overallTotalAmountWithVat,
        int $totalCardsCountOverall,    // New parameter for header
        int $activeCardsCountOverall,   // New parameter for header
        int $blockedCardsCountOverall   // New parameter for header
    ): string {
        $templatePath = storage_path('app/public/invoice_template/invoice.xls');

        if (!File::exists($templatePath)) {
            Log::error("Invoice template not found at: {$templatePath}");
            throw new Exception("Invoice template not found at: {$templatePath}");
        }

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();
            $currentDate = Carbon::now();
            $parsedPeriodStart = Carbon::parse($periodStart)->format('d.m.Y');
            $parsedPeriodEnd = Carbon::parse($periodEnd)->format('d.m.Y');

            // --- Populate Client and Invoice Data (Header) ---
            $sheet->setCellValue('B1', (string) $client->short_name); // Placeholder
            $sheet->setCellValue('B2', (string) $client->full_name);  // Placeholder
            $sheet->setCellValue('B3', (string) $client->unp);        // Placeholder
            $sheet->setCellValue('B4', (string) $client->postal_address); // Placeholder
            $bankDetails = "Р/с: {$client->bank_account_number}, BIC: {$client->bank_bic}, Адрес банка: {$client->bank_postal_address}";
            $sheet->setCellValue('B5', $bankDetails); // Placeholder

            $invoiceNumberXls = "INV-{$client->id}-{$currentDate->format('YmdHis')}";
            $sheet->setCellValue('F1', $invoiceNumberXls); // Placeholder
            $sheet->setCellValue('F2', $currentDate->format('d.m.Y')); // Placeholder
            $sheet->setCellValue('F3', "Период с {$parsedPeriodStart} по {$parsedPeriodEnd}"); // Placeholder

            // Overall Card Counts in header
            $sheet->setCellValue('D5', $totalCardsCountOverall);  // Placeholder: Total Cards
            $sheet->setCellValue('D6', $activeCardsCountOverall);  // Placeholder: Active Cards
            $sheet->setCellValue('D7', $blockedCardsCountOverall); // Placeholder: Blocked Cards

            // --- Populate Card Details (Table) ---
            $startRow = 10; // Placeholder: Starting row for card details table
            $currentRow = $startRow;

            foreach ($cardStatsForDetails as $stat) { // $cardStatsForDetails only contains cards with usage
                $bonusCard = $stat->bonusCard; // This is a CarwashBonusCard object
                $durationSeconds = (int) $stat->duration_seconds;

                $cardNumber = (string) $bonusCard->card_number;
                $ratePerMinute = (float) $bonusCard->rate_per_minute;

                $durationMinutes = 0;
                if ($durationSeconds > 0) {
                    $durationMinutes = (int) ceil($durationSeconds / 60.0);
                    if ($durationMinutes == 0) { // Ensure at least 1 minute if any seconds > 0
                        $durationMinutes = 1;
                    }
                }

                $amountForCard = $durationMinutes * $ratePerMinute;

                $sheet->setCellValue("A{$currentRow}", $cardNumber);        // Placeholder
                $sheet->setCellValue("B{$currentRow}", $ratePerMinute);     // Placeholder
                $sheet->setCellValue("C{$currentRow}", $durationMinutes);   // Placeholder
                $sheet->setCellValue("D{$currentRow}", $amountForCard);     // Placeholder
                $currentRow++;
            }

            // --- Populate Totals ---
            // These totals are the ones calculated in createAndSendInvoiceForClient and passed in.
            $totalRowPlaceholder = $currentRow + 1; // Example: totals start one row after the last card item
            $sheet->setCellValue("E{$totalRowPlaceholder}", $overallTotalAmountWithoutVat); // Placeholder
            $sheet->setCellValue("E" . ($totalRowPlaceholder + 1), $overallVatAmount ?? 0); // Placeholder
            $sheet->setCellValue("E" . ($totalRowPlaceholder + 2), $overallTotalAmountWithVat); // Placeholder

            // --- Save File ---
            $outputDir = storage_path('app/public/invoices');
            File::ensureDirectoryExists($outputDir);
            $fileName = sprintf(
                'invoice_%s_%s_%s__%d.xls',
                $currentDate->format('Y'),
                $currentDate->format('m'),
                $currentDate->format('d'),
                $client->id
            );
            $fullSavePath = $outputDir . '/' . $fileName;
            $writer = new Xls($spreadsheet);
            $writer->save($fullSavePath);
            Log::info("XLS generated for client {$client->id} at {$fullSavePath}");
            return $fullSavePath;

        } catch (Exception $e) {
            Log::error("Error in generateInvoiceXls for client {$client->id}: {$e->getMessage()}", ['exception' => $e]);
            throw $e;
        }
    }
}
