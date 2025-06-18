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
        int $totalCardsCountOverall,
        int $activeCardsCountOverall,
        int $blockedCardsCountOverall
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
            $sheet->setCellValue('A6', "Счет № 117 от 30 апреля 2025 г.");
            $sheet->setCellValue('A8', (string) $client->contract);
            $sheet->setCellValue('A9', "Заказчик: ".$client->full_name);
            $sheet->setCellValue('C25', (string) $client->full_name);
            $sheet->setCellValue('A10', "Плательщик: {$client->full_name}, адрес: {$client->postal_address}");
            $sheet->setCellValue('B11', "Р/сч: {$client->bank_account_number} в {$client->bank_postal_address} код {$client->bank_bic}, УНП:{$client->unp}");


            $sheet->setCellValue("C16", $overallTotalAmountWithoutVat);
            $sheet->setCellValue("C17", $overallTotalAmountWithoutVat);
            $sheet->setCellValue("E16", $overallVatAmount ?? 0);
            $sheet->setCellValue("E17", $overallVatAmount ?? 0);
            $sheet->setCellValue("F16", $overallTotalAmountWithVat);
            $sheet->setCellValue("F17", $overallTotalAmountWithVat);


            // --- Прописью ---
            $sheet->setCellValue('A19', "Сумма НДС: " . $this->convertToWords($overallVatAmount ?? 0));
            $sheet->setCellValue('A21', "Всего к оплате на сумму с НДС: " . $this->convertToWords($overallTotalAmountWithVat));


            // --- Детализация по картам ---
            $startRow = 39; // Первая строка с данными
            $rowNumber = 1;
            $currentRow = $startRow;

            // Период
            $parsedPeriodStart = Carbon::parse($periodStart)->format('d.m.Y');
            $parsedPeriodEnd = Carbon::parse($periodEnd)->format('d.m.Y');
            $sheet->setCellValue('A36', "Период с {$parsedPeriodStart} по {$parsedPeriodEnd}");

            foreach ($cardStatsForDetails as $stat) {
                $bonusCard = $stat->bonusCard;
                $durationSeconds = (int)$stat->duration_seconds;
                $cardNumber = (string)$bonusCard->card_number;
                $ratePerMinute = (float)$bonusCard->rate_per_minute;

                $durationMinutes = max(1, ceil($durationSeconds / 60));
                $amountForCard = $durationMinutes * $ratePerMinute;
                $vatRate = 20.0;
                $vatSum = round($amountForCard * ($vatRate / 100), 2);
                $totalWithVat = $amountForCard + $vatSum;

                // Вставка новой строки перед каждой записью
                if ($currentRow > $startRow) {
                    $sheet->insertNewRowBefore($currentRow, 1);
                }

                // Заполнение данных
                $sheet->setCellValue("A{$currentRow}", $rowNumber++);
                $sheet->setCellValue("B{$currentRow}", $cardNumber);
                $sheet->setCellValue("C{$currentRow}", $currentDate->toDateTimeString());
                $sheet->setCellValue("D{$currentRow}", $durationSeconds);
                $sheet->setCellValue("E{$currentRow}", 0);
                $sheet->setCellValue("F{$currentRow}", $ratePerMinute);
                $sheet->setCellValue("G{$currentRow}", $amountForCard);
                $sheet->setCellValue("H{$currentRow}", $vatRate);
                $sheet->setCellValue("I{$currentRow}", $vatSum);
                $sheet->setCellValue("J{$currentRow}", $totalWithVat);

                $currentRow++;
            }

            // --- Итоговая строка детализации ---
            $sheet->setCellValue("G{$currentRow}", "=SUM(G{$startRow}:G" . ($currentRow - 1) . ")");
            $sheet->setCellValue("I{$currentRow}", "=SUM(I{$startRow}:I" . ($currentRow - 1) . ")");
            $sheet->setCellValue("J{$currentRow}", "=SUM(J{$startRow}:J" . ($currentRow - 1) . ")");

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


    // Сумма прописью.
    private function convertToWords($inn, $stripkop=false): array|string|null
    {
        $nol = 'ноль';
        $str[100]= array('','сто','двести','триста','четыреста','пятьсот','шестьсот', 'семьсот', 'восемьсот','девятьсот');
        $str[11] = array('','десять','одиннадцать','двенадцать','тринадцать', 'четырнадцать','пятнадцать','шестнадцать','семнадцать', 'восемнадцать','девятнадцать','двадцать');
        $str[10] = array('','десять','двадцать','тридцать','сорок','пятьдесят', 'шестьдесят','семьдесят','восемьдесят','девяносто');
        $sex = array(
            array('','один','два','три','четыре','пять','шесть','семь', 'восемь','девять'),// m
            array('','одна','две','три','четыре','пять','шесть','семь', 'восемь','девять') // f
        );
        $forms = array(
            array('копейка', 'копейки', 'копеек', 1), // 10^-2
            array('белорусский рубль', 'белорусских рубля', 'белорусских рублей',  0), // 10^ 0
            array('тысяча', 'тысячи', 'тысяч', 1), // 10^ 3
            array('миллион', 'миллиона', 'миллионов',  0), // 10^ 6
            array('миллиард', 'миллиарда', 'миллиардов',  0), // 10^ 9
            array('триллион', 'триллиона', 'триллионов',  0), // 10^12
        );
        $out = $tmp = array();
        // Поехали!
        $tmp = explode('.', str_replace(',','.', $inn));
        $rub = number_format($tmp[ 0], 0,'','-');
        if ($rub== 0) $out[] = $nol;
        // нормализация копеек
        $kop = isset($tmp[1]) ? substr(str_pad($tmp[1], 2, '0', STR_PAD_RIGHT), 0,2) : '00';
        $segments = explode('-', $rub);
        $offset = sizeof($segments);
        if ((int)$rub== 0) { // если 0 рублей
            $o[] = $nol;
            $o[] = $this->morph( 0, $forms[1][ 0],$forms[1][1],$forms[1][2]);
        }
        else {
            foreach ($segments as $k=>$lev) {
                $sexi= (int) $forms[$offset][3]; // определяем род
                $ri = (int) $lev; // текущий сегмент
                if ($ri== 0 && $offset>1) {// если сегмент==0 & не последний уровень(там Units)
                    $offset--;
                    continue;
                }
                // нормализация
                $ri = str_pad($ri, 3, '0', STR_PAD_LEFT);
                // получаем циферки для анализа
                $r1 = (int)substr($ri, 0,1); //первая цифра
                $r2 = (int)substr($ri,1,1); //вторая
                $r3 = (int)substr($ri,2,1); //третья
                $r22= (int)$r2.$r3; //вторая и третья
                // разгребаем порядки
                if ($ri>99) $o[] = $str[100][$r1]; // Сотни
                if ($r22>20) {// >20
                    $o[] = $str[10][$r2];
                    $o[] = $sex[ $sexi ][$r3];
                }
                else { // <=20
                    if ($r22>9) $o[] = $str[11][$r22-9]; // 10-20
                    elseif($r22> 0) $o[] = $sex[ $sexi ][$r3]; // 1-9
                }
                // Рубли
                $o[] = $this->morph($ri, $forms[$offset][ 0],$forms[$offset][1],$forms[$offset][2]);
                $offset--;
            }
        }
        // Копейки
        if (!$stripkop) {
            $o[] = $kop;
            $o[] = $this->morph($kop,$forms[ 0][ 0],$forms[ 0][1],$forms[ 0][2]);
        }

        // Формируем строку
        $result = preg_replace("/\s{2,}/", ' ', implode(' ', $o));

        // Делаем первую букву заглавной
        $result = mb_strtoupper(mb_substr($result, 0, 1)) . mb_substr($result, 1);

        // Если есть копейки, заменяем пробел перед ними на запятую
        if (!$stripkop && strpos($result, ' копеек')) {
            $parts = preg_split('/\s(?=\d{2}\sкопеек)/u', $result, 2);
            if (count($parts) === 2) {
                $result = $parts[0] . ', ' . $parts[1];
            }
        }

        return $result;
    }

    /**
     * Склоняем словоформу
     */
    private function morph($n, $f1, $f2, $f5) {
        $n = abs($n) % 100;
        $n1= $n % 10;
        if ($n>10 && $n<20) return $f5;
        if ($n1>1 && $n1<5) return $f2;
        if ($n1==1) return $f1;
        return $f5;
    }
}
