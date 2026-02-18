<?php

namespace App\Services;

use App\Models\CarwashClient;
use App\Models\CarwashBonusCardStat;
use App\Models\CarwashInvoice;
use App\Mail\CarwashInvoiceMail;
use App\Mail\CarwashInvoiceDuplicateMail;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CarwashInvoiceService
{
    /**
     * Creates and sends an invoice for a given client for a specific month and year.
     *
     * @param CarwashClient $client
     * @param int $month
     * @param int $year
     * @param bool $sendEmail Indicates whether to send the invoice via email to the client.
     * @return bool True on success, false on failure.
     */
    public function createAndSendInvoiceForClient(CarwashClient $client, int $month, int $year, bool $sendEmail = true): bool
    {
        Log::info("Processing invoice for client ID: {$client->id}, period: {$year}-{$month}, send email: " . ($sendEmail ? 'yes' : 'no'));

        DB::beginTransaction();
        try {
            [$periodStart, $periodEnd] = $this->getReportingPeriodForMonthYear($year, $month);

            // Delete existing invoices for this client and period
            CarwashInvoice::where('client_id', $client->id)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->each(function ($invoice) {
                    if ($invoice->file_path) {
                        $publicRelativePath = str_replace('public/', '', $invoice->file_path);
                        if (Storage::disk('public')->exists($publicRelativePath)) {
                            Storage::disk('public')->delete($publicRelativePath);
                            Log::info("Deleted old invoice file from public disk: {$publicRelativePath}");
                        }
                    }
                    $invoice->delete();
                });
            Log::info("Deleted existing DB invoice entries for client ID: {$client->id} for period {$periodStart->toDateString()} - {$periodEnd->toDateString()}");

            $client->load('bonusCards');
            [$total, $active, $blocked] = $this->getCardCounts($client);

            // Single method that returns aggregated data and detailed stats
            [$cardStats, $amountWithoutVat, $detailedStats] = $this->gatherUsageStats($client, $periodStart, $periodEnd);

            $nextInvoiceNumber = (CarwashInvoice::max('id') ?? 0) + 1;

            if (empty($cardStats) || $amountWithoutVat <= 0) {
                $vatConfig = $this->calculateVat(0);
                CarwashInvoice::create([
                    'client_id' => $client->id,
                    'amount' => $vatConfig['amountWithVat'],
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'total_cards_count' => $total,
                    'active_cards_count' => $active,
                    'blocked_cards_count' => $blocked,
                    'file_path' => null,
                    'sent_at' => now(),
                    'sent_to_email_at' => null,
                ]);
                Log::info("Client ID {$client->id} has no usage or zero amount. Zero-amount invoice saved.");
                DB::commit();
                return true;
            }

            $vatConfig = $this->calculateVat($amountWithoutVat);
            $xlsRelativePath = $this->generateInvoiceXls(
                $client,
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
                $detailedStats,
                $amountWithoutVat,
                $vatConfig['vatAmount'],
                $vatConfig['amountWithVat'],
                $vatConfig['vatRate'],
                $nextInvoiceNumber
            );

            $invoice = CarwashInvoice::create([
                'client_id' => $client->id,
                'amount' => $vatConfig['amountWithVat'],
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'total_cards_count' => $total,
                'active_cards_count' => $active,
                'blocked_cards_count' => $blocked,
                'file_path' => $xlsRelativePath,
                'sent_at' => now(),
                'sent_to_email_at' => null,
            ]);

            // Send to client if requested
            if ($sendEmail) {
                $this->sendInvoiceToClient($client, $invoice, $xlsRelativePath);
            } else {
                Log::info("Invoice successfully generated BUT NOT EMAILED for client ID: {$client->id} as per request.");
            }

            DB::commit();

            // Send duplicate to chief accountant if enabled and amount > 0 and send requested
            if ($invoice->amount > 0 && config('mail.send_mail_duplicate_buh', true) && $sendEmail) {
                $this->sendDuplicateToAccountant($client, $invoice, $xlsRelativePath);
            }

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Invoice processing failed for client ID {$client->id}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send invoice email to the client.
     *
     * @param CarwashClient $client
     * @param CarwashInvoice $invoice
     * @param string $xlsRelativePath
     * @return void
     */
    public function sendInvoiceToClient(CarwashClient $client, CarwashInvoice $invoice, string $xlsRelativePath): bool
    {
        if (empty($client->email)) {
            Log::warning("Client ID {$client->id} has no email address. Invoice not sent via email.");
            return false;
        }

        $absolutePathToAttach = Storage::disk('public')->path(str_replace('public/', '', $xlsRelativePath));

        if (!File::exists($absolutePathToAttach)) {
            Log::error("Invoice file not found at {$absolutePathToAttach} for client ID {$client->id}. Email not sent.");
            return false;
        }

        try {
            Mail::to($client->email)->send(new CarwashInvoiceMail($client, $invoice, $absolutePathToAttach));
            $invoice->sent_to_email_at = now();
            $invoice->save();
            Log::info("Invoice successfully emailed to client ID: {$client->id}");
            return true;
        } catch (Exception $e) {
            Log::error("Failed to send invoice email to client ID {$client->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Send duplicate invoice email to the chief accountant.
     *
     * @param CarwashClient $client
     * @param CarwashInvoice $invoice
     * @param string $xlsRelativePath
     * @return void
     */
    public function sendDuplicateToAccountant(CarwashClient $client, CarwashInvoice $invoice, string $xlsRelativePath): bool
    {
        $accountantEmail = config('mail.mail_duplicate_address', '');
        if (empty($accountantEmail)) {
            Log::warning("Accountant email is not configured. Duplicate invoice not sent.");
            return false;
        }

        $absolutePathToAttach = Storage::disk('public')->path(str_replace('public/', '', $xlsRelativePath));

        if (!File::exists($absolutePathToAttach)) {
            Log::error("Invoice file not found at {$absolutePathToAttach} for duplicate email. Cannot send to accountant.");
            return false;
        }

        try {
            Mail::to($accountantEmail)->send(new CarwashInvoiceDuplicateMail($client, $invoice, $absolutePathToAttach));
            Log::info("Duplicate invoice sent to chief accountant for client ID: {$client->id}");
            return true;
        } catch (Exception $e) {
            Log::error("Failed to send duplicate invoice email to chief accountant for client ID {$client->id}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get start and end of the reporting period (month).
     *
     * @param int $year
     * @param int $month
     * @return array [Carbon $start, Carbon $end]
     */
    private function getReportingPeriodForMonthYear(int $year, int $month): array
    {
        $date = Carbon::createFromDate($year, $month, 1);
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();
        return [$start, $end];
    }

    /**
     * Count total, active and blocked cards for the client.
     *
     * @param CarwashClient $client
     * @return array [int $total, int $active, int $blocked]
     */
    private function getCardCounts(CarwashClient $client): array
    {
        $total = $client->bonusCards->count();
        $active = $client->bonusCards->where('status', 'active')->count();
        $blocked = $client->bonusCards->where('status', 'blocked')->count();
        return [$total, $active, $blocked];
    }

    /**
     * Get detailed usage statistics for all active cards of the client within the period.
     * Enriches each record with calculated financial data.
     *
     * @param CarwashClient $client
     * @param Carbon $start
     * @param Carbon $end
     * @return Collection
     */
    private function getDetailedUsageStats(CarwashClient $client, Carbon $start, Carbon $end): Collection
    {
        $activeCardIds = $client->bonusCards()
            ->where('status', 'active')
            ->pluck('id');

        if ($activeCardIds->isEmpty()) {
            return collect();
        }

        $stats = CarwashBonusCardStat::with('card')
            ->whereIn('card_id', $activeCardIds)
            ->where('duration_seconds', '>', 0)
            ->whereBetween('start_time', [$start, $end])
            ->orderBy('start_time')
            ->get();

        foreach ($stats as $stat) {
            $durationMinutes = max(1, (int) ceil($stat->duration_seconds / 60));
            $amount = $durationMinutes * $stat->card->rate_per_minute;
            $vatData = $this->calculateVat($amount);

            $stat->duration_minutes_calculated = $durationMinutes;
            $stat->amount_without_vat = $amount;
            $stat->vat_rate = $vatData['vatRate'];
            $stat->vat_amount = $vatData['vatAmount'];
            $stat->total_with_vat = $vatData['amountWithVat'];
        }

        return $stats;
    }

    /**
     * Gather usage statistics: aggregated per card, total amount without VAT, and detailed stats.
     *
     * @param CarwashClient $client
     * @param Carbon $start
     * @param Carbon $end
     * @return array [array $cardStats, float $totalAmountWithoutVat, Collection $detailedStats]
     */
    private function gatherUsageStats(CarwashClient $client, Carbon $start, Carbon $end): array
    {
        $detailedStats = $this->getDetailedUsageStats($client, $start, $end);
        $grouped = $detailedStats->groupBy('card_id');

        $cardStats = [];
        $totalAmount = 0.0;

        foreach ($client->bonusCards->where('status', 'active') as $card) {
            $cardSessions = $grouped->get($card->id, collect());
            if ($cardSessions->isNotEmpty()) {
                $cardStats[] = (object) [
                    'bonusCard' => $card,
                    'duration_seconds' => $cardSessions->sum('duration_seconds'),
                    'total_amount' => $cardSessions->sum('amount_without_vat'),
                ];
                $totalAmount += $cardSessions->sum('amount_without_vat');
            }
        }

        return [$cardStats, $totalAmount, $detailedStats];
    }

    /**
     * Calculate VAT based on configuration.
     *
     * @param float $amount
     * @return array ['vatAmount' => float, 'amountWithVat' => float, 'vatRate' => int]
     */
    private function calculateVat(float $amount): array
    {
        $calcVat = config('invoice.calculate_vat', false);
        $vatPercent = config('invoice.vat_percentage', 0.20);
        $vat = $calcVat ? $amount * $vatPercent : 0.0;
        return [
            'vatAmount' => $vat,
            'amountWithVat' => $amount + $vat,
            'vatRate' => round($vatPercent * 100),
        ];
    }

    /**
     * Generate an XLS invoice file from a template.
     *
     * @param CarwashClient $client
     * @param string $periodStart YYYY-MM-DD
     * @param string $periodEnd   YYYY-MM-DD
     * @param Collection $detailedStats
     * @param float $overallTotalAmountWithoutVat
     * @param float|null $overallVatAmount
     * @param float $overallTotalAmountWithVat
     * @param int $overallVatRate
     * @param int $nextInvoiceNumber
     * @return string Relative path (public/invoices/...)
     * @throws Exception
     */
    public function generateInvoiceXls(
        CarwashClient $client,
        string $periodStart,
        string $periodEnd,
        Collection $detailedStats,
        float $overallTotalAmountWithoutVat,
        ?float $overallVatAmount,
        float $overallTotalAmountWithVat,
        int $overallVatRate,
        int $nextInvoiceNumber
    ): string {
        $templatePath = storage_path('app/public/invoice_template/invoice.xls');

        if (!File::exists($templatePath)) {
            Log::error("Invoice template not found at: {$templatePath}");
            throw new Exception("Invoice template not found at: {$templatePath}");
        }

        try {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();

            $parsedPeriodStart = Carbon::parse($periodStart)->format('d.m.Y');
            $parsedPeriodEnd = Carbon::parse($periodEnd)->format('d.m.Y');
            $sheet->setCellValue('A36', "Период с {$parsedPeriodStart} по {$parsedPeriodEnd}");

            // Header data
            $sheet->setCellValue('A6', "АКТ № AM-{$nextInvoiceNumber} от {$parsedPeriodEnd} г.");
            $sheet->setCellValue('A8', (string) $client->contract);
            $sheet->setCellValue('A9', "Заказчик: " . $client->full_name);
            $sheet->setCellValue('C25', (string) $client->full_name);
            $sheet->setCellValue('A10', "Р/сч: {$client->bank_account_number} в {$client->bank_postal_address} код {$client->bank_bic}");
            $sheet->setCellValue('A11', "УНП:{$client->unp}");
            $sheet->setCellValue('A12', "Адрес: {$client->postal_address}");

            $sheet->setCellValue("C16", $overallTotalAmountWithoutVat);
            $sheet->setCellValue("D16", $overallVatRate);
            $sheet->setCellValue("C17", $overallTotalAmountWithoutVat);
            $sheet->setCellValue("E16", $overallVatAmount ?? 0);
            $sheet->setCellValue("E17", $overallVatAmount ?? 0);
            $sheet->setCellValue("F16", $overallTotalAmountWithVat);
            $sheet->setCellValue("F17", $overallTotalAmountWithVat);

            $sheet->setCellValue('A19', "Всего оказано услуг на сумму: " . $this->convertToWords($overallTotalAmountWithVat ?? 0) . ", в т.ч.: НДС - " . $this->convertToWords($overallVatAmount ?? 0));

            // Detailed rows
            $startRow = 39;
            $currentRow = $startRow;
            $globalCounter = 1;

            $grouped = $detailedStats->groupBy('card_id');

            foreach ($grouped as $cardId => $sessions) {
                $card = $sessions->first()->card;

                // Card header
                $sheet->setCellValue("B{$currentRow}", "{$card->name} ({$card->card_number})");
                $sheet->getStyle("B{$currentRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $currentRow++;

                // Detail lines
                foreach ($sessions as $stat) {
                    $sheet->setCellValue("A{$currentRow}", $globalCounter++);
                    $sheet->setCellValue("C{$currentRow}", Carbon::parse($stat->start_time)->format('d.m.Y H:i'));
                    $sheet->setCellValue("D{$currentRow}", $stat->duration_seconds);
                    $sheet->setCellValue("E{$currentRow}", $stat->card->rate_per_minute);
                    $sheet->setCellValue("F{$currentRow}", $stat->amount_without_vat);
                    $sheet->setCellValue("G{$currentRow}", $stat->vat_rate);
                    $sheet->getStyle("G{$currentRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0"%"');
                    $sheet->setCellValue("H{$currentRow}", $stat->vat_amount);
                    $sheet->setCellValue("I{$currentRow}", $stat->total_with_vat);
                    $sheet->getStyle("A{$currentRow}:I{$currentRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $currentRow++;
                }
            }

            // Totals
            $sheet->setCellValue("C{$currentRow}", "ИТОГО:");
            $sheet->setCellValue("D{$currentRow}", "=SUM(D{$startRow}:D" . ($currentRow - 1) . ")");
            $sheet->setCellValue("F{$currentRow}", "=SUM(F{$startRow}:F" . ($currentRow - 1) . ")");
            $sheet->setCellValue("H{$currentRow}", "=SUM(H{$startRow}:H" . ($currentRow - 1) . ")");
            $sheet->setCellValue("I{$currentRow}", "=SUM(I{$startRow}:I" . ($currentRow - 1) . ")");

            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("D{$currentRow}:I{$currentRow}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $sheet->getStyle("D{$currentRow}")
                ->getNumberFormat()
                ->setFormatCode("# ##0");
            $sheet->getStyle("E{$currentRow}:F{$currentRow}")
                ->getNumberFormat()
                ->setFormatCode("# ##0.00");
            $sheet->getStyle("H{$currentRow}:I{$currentRow}")
                ->getNumberFormat()
                ->setFormatCode("# ##0.00");

            // Save file
            $publicOutputDir = storage_path('app/public/invoices/' . $client->id . '/' . Carbon::parse($periodStart)->format('Y-m'));
            File::ensureDirectoryExists($publicOutputDir);

            $fileName = sprintf(
                'invoice_%s_client_%d_num_%d.xls',
                Carbon::parse($periodStart)->format('Y-m-d'),
                $client->id,
                $nextInvoiceNumber
            );
            $fullSavePath = $publicOutputDir . '/' . $fileName;
            $relativePathForDb = 'public/invoices/' . $client->id . '/' . Carbon::parse($periodStart)->format('Y-m') . '/' . $fileName;

            $writer = new Xls($spreadsheet);
            $writer->save($fullSavePath);
            Log::info("XLS generated for client {$client->id} at {$fullSavePath}");

            return $relativePathForDb;
        } catch (Exception $e) {
            Log::error("Error in generateInvoiceXls for client {$client->id}: {$e->getMessage()}", ['exception' => $e]);
            throw $e;
        }
    }

    // ---------- Helper methods for amount in words ----------

    private function convertToWords($inn, $stripkop = false): array|string|null
    {
        $nol = 'ноль';
        $str[100] = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];
        $str[11] = ['', 'десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать', 'двадцать'];
        $str[10] = ['', 'десять', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];
        $sex = [
            ['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'], // m
            ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять']  // f
        ];
        $forms = [
            ['копейка', 'копейки', 'копеек', 1],
            ['белорусский рубль', 'белорусских рубля', 'белорусских рублей', 0],
            ['тысяча', 'тысячи', 'тысяч', 1],
            ['миллион', 'миллиона', 'миллионов', 0],
            ['миллиард', 'миллиарда', 'миллиардов', 0],
            ['триллион', 'триллиона', 'триллионов', 0],
        ];

        $out = [];
        $tmp = explode('.', str_replace(',', '.', $inn));
        $rub = number_format($tmp[0], 0, '', '-');
        if ($rub == 0) $out[] = $nol;

        $kop = isset($tmp[1]) ? substr(str_pad($tmp[1], 2, '0', STR_PAD_RIGHT), 0, 2) : '00';
        $segments = explode('-', $rub);
        $offset = count($segments);

        if ((int)$rub == 0) {
            $o[] = $nol;
            $o[] = $this->morph(0, $forms[1][0], $forms[1][1], $forms[1][2]);
        } else {
            foreach ($segments as $k => $lev) {
                $sexi = (int)$forms[$offset][3];
                $ri = (int)$lev;
                if ($ri == 0 && $offset > 1) {
                    $offset--;
                    continue;
                }
                $ri = str_pad($ri, 3, '0', STR_PAD_LEFT);
                $r1 = (int)substr($ri, 0, 1);
                $r2 = (int)substr($ri, 1, 1);
                $r3 = (int)substr($ri, 2, 1);
                $r22 = (int)($r2 . $r3);

                if ($ri > 99) $o[] = $str[100][$r1];
                if ($r22 > 20) {
                    $o[] = $str[10][$r2];
                    $o[] = $sex[$sexi][$r3];
                } else {
                    if ($r22 > 9) $o[] = $str[11][$r22 - 9];
                    elseif ($r22 > 0) $o[] = $sex[$sexi][$r3];
                }
                $o[] = $this->morph($ri, $forms[$offset][0], $forms[$offset][1], $forms[$offset][2]);
                $offset--;
            }
        }

        if (!$stripkop) {
            $o[] = $kop;
            $o[] = $this->morph($kop, $forms[0][0], $forms[0][1], $forms[0][2]);
        }

        $result = preg_replace("/\s{2,}/", ' ', implode(' ', $o));
        $result = mb_strtoupper(mb_substr($result, 0, 1)) . mb_substr($result, 1);

        if (!$stripkop && strpos($result, ' копеек')) {
            $parts = preg_split('/\s(?=\d{2}\sкопеек)/u', $result, 2);
            if (count($parts) === 2) {
                $result = $parts[0] . ', ' . $parts[1];
            }
        }

        return $result;
    }

    private function morph($n, $f1, $f2, $f5): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) return $f5;
        if ($n1 > 1 && $n1 < 5) return $f2;
        if ($n1 == 1) return $f1;
        return $f5;
    }
}
