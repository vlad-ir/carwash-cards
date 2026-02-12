<?php

namespace App\Services;

use App\Models\CarwashClient;
use App\Models\CarwashBonusCard;
use App\Models\CarwashBonusCardStat;
use App\Models\CarwashInvoice;
use App\Mail\CarwashInvoiceMail;
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

class CarwashInvoiceService
{
    /**
     * Creates and sends an invoice for a given client for a specific month and year.
     *
     * @param CarwashClient $client
     * @param int $month
     * @param int $year
     * @param bool $sendEmail Indicates whether to send the invoice via email.
     * @return bool True on success, false on failure.
     */
    public function createAndSendInvoiceForClient(CarwashClient $client, int $month, int $year, bool $sendEmail = true): bool
    {
        Log::info("Processing invoice for client ID: {$client->id}, period: {$year}-{$month}, send email: " . ($sendEmail ? 'yes' : 'no'));

        DB::beginTransaction();
        try {
            [$periodStart, $periodEnd] = $this->getReportingPeriodForMonthYear($year, $month);

            // Удаляем существующие счета для этого клиента за этот период
            CarwashInvoice::where('client_id', $client->id)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->each(function ($invoice) {
                    if ($invoice->file_path) {
                        // file_path хранится как 'public/invoices/...'
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
            [$cardStats, $amountWithoutVat] = $this->gatherUsageStats($client, $periodStart, $periodEnd);

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
                    'sent_at' => now(), // Поле 'sent_at' может быть использовано для отслеживания времени создания
                    'sent_to_email_at' => null, // Не отправляем email для нулевого счета
                ]);
                Log::info("Client ID {$client->id} has no usage or zero amount. Zero-amount invoice saved.");
                DB::commit();
                return true;
            }

            $vatConfig = $this->calculateVat($amountWithoutVat);
            $xlsRelativePath = $this->generateInvoiceXls(
                $client, $periodStart->toDateString(), $periodEnd->toDateString(),
                $cardStats, $amountWithoutVat,
                $vatConfig['vatAmount'], $vatConfig['amountWithVat'], $vatConfig['vatRate'],
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
                'sent_at' => now(), // Время создания
                'sent_to_email_at' => null, // Инициализируем как null
            ]);

            if ($sendEmail) {
                if (empty($client->email)) {
                    Log::warning("Client ID {$client->id} has no email address. Invoice not sent via email.");
                    // Не обновляем sent_to_email_at, оно останется null
                } else {
                    // $xlsRelativePath теперь 'public/invoices/...'
                    // Для аттача нужен абсолютный путь к файлу на диске.
                    $absolutePathToAttach = Storage::disk('public')->path(str_replace('public/', '', $xlsRelativePath));

                    if (!File::exists($absolutePathToAttach)) {
                        Log::error("Invoice file not found at {$absolutePathToAttach} for client ID {$client->id}. Email not sent.");
                        // Можно решить, считать ли это ошибкой для $errorCount в контроллере
                        // Не обновляем sent_to_email_at
                    } else {
                        Mail::to($client->email)->send(new CarwashInvoiceMail($client, $invoice, $absolutePathToAttach));
                        $invoice->sent_to_email_at = now();
                        $invoice->save(); // Сохраняем время отправки
                        Log::info("Invoice successfully generated AND EMAILED for client ID: {$client->id}");
                    }
                }
            } else {
                Log::info("Invoice successfully generated BUT NOT EMAILED for client ID: {$client->id} as per request.");
            }

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Invoice processing failed for client ID {$client->id}: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    private function getReportingPeriodForMonthYear(int $year, int $month): array
    {
        $date = Carbon::createFromDate($year, $month, 1);
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();
        return [$start, $end];
    }

    private function getCardCounts(CarwashClient $client): array
    {
        $total = $client->bonusCards->count();
        $active = $client->bonusCards->where('status', 'active')->count();
        $blocked = $client->bonusCards->where('status', 'blocked')->count();
        return [$total, $active, $blocked];
    }

    private function gatherUsageStats(CarwashClient $client, Carbon $start, Carbon $end): array
    {
        $activeCards = $client->bonusCards->where('status', 'active');
        $cardStats = [];
        $totalAmount = 0.0;

        foreach ($activeCards as $card) {
            $usage = CarwashBonusCardStat::where('card_id', $card->id)
                ->where('duration_seconds', '>', 0)
                ->whereBetween('start_time', [$start, $end])
                ->get();

            $seconds = $usage->sum('duration_seconds');
            if ($seconds > 0) {
                $cardStats[] = (object) ['bonusCard' => $card, 'duration_seconds' => $seconds];
                $minutes = max(1, (int) ceil($seconds / 60));
                $totalAmount += $minutes * $card->rate_per_minute;
            }
        }

        return [$cardStats, $totalAmount];
    }

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
     * @param int $overallVatRate
     * @param int $nextInvoiceNumber
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
            $currentDate = Carbon::now();

            // Период
            $parsedPeriodStart = Carbon::parse($periodStart)->format('d.m.Y');
            $parsedPeriodEnd = Carbon::parse($periodEnd)->format('d.m.Y');
            $sheet->setCellValue('A36', "Период с {$parsedPeriodStart} по {$parsedPeriodEnd}");

            // --- Populate Client and Invoice Data (Header) ---
            $sheet->setCellValue('A6', "АКТ № AM-{$nextInvoiceNumber} от {$parsedPeriodEnd} г.");
            $sheet->setCellValue('A8', (string) $client->contract);
            $sheet->setCellValue('A9', "Заказчик: ".$client->full_name);
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

            // --- Прописью ---
            $sheet->setCellValue('A19', "Всего оказано услуг  на сумму: " . $this->convertToWords($overallTotalAmountWithVat ?? 0). ", в т.ч.: НДС - ".$this->convertToWords($overallVatAmount ?? 0));


            // --- Детализация по картам ---
            $startRow = 39; // Первая строка с данными
            $currentRow = $startRow;
            $globalCounter = 1;

            foreach ($client->bonusCards->where('status', 'active') as $card) {

                $cardStats = CarwashBonusCardStat::where('card_id', $card->id)
                    ->whereBetween('start_time', [
                        Carbon::parse($periodStart)->startOfDay(),
                        Carbon::parse($periodEnd)->endOfDay()
                    ])
                    ->where('duration_seconds', '>', 0)
                    ->orderBy('start_time')
                    ->get();

                if ($cardStats->isEmpty()) continue;

                // Название карты и статус
                $sheet->setCellValue("B{$currentRow}", (string) "{$card->name} ({$card->card_number})");
                $sheet->getStyle("B{$currentRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $currentRow++;

                foreach ($cardStats as $stat) {
                    $durationSeconds = (int)$stat->duration_seconds;
                    $remainingBalanceSeconds = (int)$stat->remaining_balance_seconds;
                    $ratePerMinute = (float)$card->rate_per_minute;

                    $durationMinutes = max(1, ceil($durationSeconds / 60));
                    $amountForCard = $durationMinutes * $ratePerMinute;

                    $vatData = $this->calculateVat($amountForCard);
                    $vatRate = $vatData['vatRate'];
                    $vatSum = $vatData['vatAmount'];
                    $totalWithVat = $amountForCard + $vatSum;

                    $sheet->setCellValue("A{$currentRow}", $globalCounter++);
                    $sheet->setCellValue("C{$currentRow}", Carbon::parse($stat->start_time)->format('d.m.Y H:i'));
                    $sheet->setCellValue("D{$currentRow}", $durationSeconds);
                    //$sheet->setCellValue("E{$currentRow}", $remainingBalanceSeconds);
                    $sheet->setCellValue("E{$currentRow}", $ratePerMinute);
                    $sheet->setCellValue("F{$currentRow}", $amountForCard);
                    $sheet->setCellValue("G{$currentRow}", $vatRate);
                    $sheet->getStyle("G{$currentRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0"%"');
                    $sheet->setCellValue("H{$currentRow}", $vatSum);
                    $sheet->setCellValue("I{$currentRow}", $totalWithVat);
                    $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);

                    $currentRow++;
                }

            }

            // --- Итог по всем картам ---
            $sheet->setCellValue("C{$currentRow}", "ИТОГО:");
            $sheet->setCellValue("D{$currentRow}", "=SUM(D{$startRow}:D" . ($currentRow - 1) . ")");
            //$sheet->setCellValue("E{$currentRow}", "=SUM(E{$startRow}:E" . ($currentRow - 1) . ")");
            $sheet->setCellValue("F{$currentRow}", "=SUM(F{$startRow}:F" . ($currentRow - 1) . ")");
            $sheet->setCellValue("H{$currentRow}", "=SUM(H{$startRow}:H" . ($currentRow - 1) . ")");
            $sheet->setCellValue("I{$currentRow}", "=SUM(I{$startRow}:I" . ($currentRow - 1) . ")");
            $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getFont()->setBold(true);
            $sheet->getStyle("D{$currentRow}:I{$currentRow}")->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            $sheet->getStyle("D{$currentRow}")
                ->getNumberFormat()
                ->setFormatCode("# ##0");

            $sheet->getStyle("E{$currentRow}:F{$currentRow}")
                ->getNumberFormat()
                ->setFormatCode("# ##0.00");


            $sheet->getStyle("H{$currentRow}:I{$currentRow}")
                ->getNumberFormat()
                ->setFormatCode("# ##0.00");



            // --- Save File ---
            $publicOutputDir = storage_path('app/public/invoices/' . $client->id . '/' . Carbon::parse($periodStart)->format('Y-m'));
            File::ensureDirectoryExists($publicOutputDir);

            $fileName = sprintf(
                'invoice_%s_client_%d_num_%d.xls',
                Carbon::parse($periodStart)->format('Y-m-d'),
                $client->id,
                $nextInvoiceNumber
            );
            $fullSavePath = $publicOutputDir . '/' . $fileName;

            // Относительный путь для сохранения в БД
            $relativePathForDb = 'public/invoices/' . $client->id . '/' . Carbon::parse($periodStart)->format('Y-m') . '/' . $fileName;

            $writer = new Xls($spreadsheet);
            $writer->save($fullSavePath);
            Log::info("XLS generated for client {$client->id} at {$fullSavePath}");
            return $relativePathForDb; // Возвращаем относительный путь для public диска

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
