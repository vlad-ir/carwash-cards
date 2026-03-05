<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\CarwashInvoice;
use App\Models\CarwashClient;
use Carbon\Carbon;

class CheckStuckEmailsCommand extends Command
{
    protected $signature = 'emails:check-stuck {--days=3 : Порог в днях} {--debug : Показать отладочную информацию}';
    protected $description = 'Проверка зависших писем со счетами в очереди emails';

    public function handle()
    {
        $threshold = Carbon::now()->subDays((int)$this->option('days'));

        $stuckJobs = DB::table('jobs')
            ->where('queue', 'emails')
            ->where('created_at', '<', $threshold)
            ->get();

        if ($stuckJobs->isEmpty()) {
            $this->info('Зависших писем нет');
            return 0;
        }

        if ($this->option('debug')) {
            $this->info("Найдено задач: " . $stuckJobs->count());
        }

        $invoiceData = [];

        foreach ($stuckJobs as $job) {
            $payload = json_decode($job->payload, true);

            if (!isset($payload['data']['command'])) {
                continue;
            }

            $command = $payload['data']['command'];
            $ids = $this->extractIdsFromSerializedCommand($command);

            if ($this->option('debug')) {
                $this->info("Job {$job->id}: invoice_id={$ids['invoice_id']}, client_id={$ids['client_id']}, isDuplicate=" . ($ids['is_duplicate'] ? '1' : '0') . ", email={$ids['recipient_email']}");
            }

            if (!$ids['invoice_id'] || !$ids['client_id']) {
                continue;
            }

            $invoiceId = $ids['invoice_id'];
            $isDuplicate = $ids['is_duplicate'];

            // Инициализируем структуру если ещё нет
            if (!isset($invoiceData[$invoiceId])) {
                $invoiceData[$invoiceId] = [
                    'client_id' => $ids['client_id'],
                    'stuck_since' => $job->created_at,
                    'recipients' => [],
                ];
            }

            // Определяем тип получателя и email
            $recipientType = $isDuplicate ? 'buh' : 'client';
            $recipientEmail = $isDuplicate
                ? (config('mail.mail_duplicate_address') ?: 'бухгалтер (не указан)')
                : ($ids['recipient_email'] ?: 'клиент (не указан)');

            // Сохраняем получателя
            $invoiceData[$invoiceId]['recipients'][$recipientType] = $recipientEmail;

            if ($this->option('debug')) {
                $this->info("  -> Добавлен получатель: {$recipientType} = {$recipientEmail}");
            }
        }

        if (empty($invoiceData)) {
            $this->error('Не удалось извлечь данные из задач очереди');
            return 1;
        }

        if ($this->option('debug')) {
            $this->info("Сгруппировано счетов: " . count($invoiceData));
            foreach ($invoiceData as $invId => $data) {
                $this->info("  Счет {$invId}: получатели = " . json_encode($data['recipients']));
            }
        }

        // Загружаем данные из БД
        $invoiceIds = array_keys($invoiceData);
        $clientIds = array_unique(array_column($invoiceData, 'client_id'));

        $invoices = CarwashInvoice::whereIn('id', $invoiceIds)
            ->with('client')
            ->get()
            ->keyBy('id');

        $clients = CarwashClient::whereIn('id', $clientIds)
            ->get()
            ->keyBy('id');

        $stuckInvoices = [];

        foreach ($invoiceData as $invoiceId => $data) {
            $invoice = $invoices->get($invoiceId);
            $client = $clients->get($data['client_id']);

            if (!$invoice || !$client) {
                if ($this->option('debug')) {
                    $this->warn("Пропущен invoice_id={$invoiceId}: invoice=" . ($invoice ? 'ok' : 'null') . ", client=" . ($client ? 'ok' : 'null'));
                }
                continue;
            }

            $stuckInvoices[] = [
                'invoice_id' => $invoiceId,
                'invoice_number' => $this->extractInvoiceNumber($invoice->file_path),
                'period' => $invoice->period_start->format('m.Y') . ' - ' . $invoice->period_end->format('m.Y'),
                'amount' => $invoice->amount,
                'client' => [
                    'id' => $client->id,
                    'name' => $client->full_name,
                    'unp' => $client->unp,
                    'short_name' => $client->short_name,
                ],
                'stuck_since' => Carbon::parse($data['stuck_since'])->format('d.m.Y H:i'),
                'recipients' => $data['recipients'], // Передаём получателей
            ];
        }

        if (empty($stuckInvoices)) {
            $this->error('Не удалось сопоставить счета с клиентами');
            return 1;
        }

        if ($this->option('debug')) {
            $this->info("Итоговое количество счетов для отправки: " . count($stuckInvoices));
            foreach ($stuckInvoices as $inv) {
                $this->info("  Счет {$inv['invoice_number']}: получатели = " . json_encode($inv['recipients']));
            }
        }

        // Отправляем в Telegram напрямую через HTTP
        $sent = $this->sendToTelegram($stuckInvoices, count($stuckJobs));

        if ($sent) {
            $this->info("✓ Отправлено уведомление о " . count($stuckInvoices) . " зависших счетах");
        } else {
            $this->error("✗ Не удалось отправить уведомление в Telegram");
        }

        return $sent ? 0 : 1;
    }

    /**
     * Отправка сообщения в Telegram через HTTP API
     */
    private function sendToTelegram(array $invoices, int $totalJobs): bool
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        if (empty($token) || empty($chatId)) {
            $this->error('TELEGRAM_BOT_TOKEN или TELEGRAM_CHAT_ID не заданы в .env');
            return false;
        }

        if ($this->option('debug')) {
            $this->info("Token: " . substr($token, 0, 10) . "...");
            $this->info("Chat ID: {$chatId}");
        }

        $message = $this->formatMessage($invoices, $totalJobs);

        // Логируем сообщение для отладки
        Log::info('Telegram message prepared', ['message' => $message]);

        try {
            $response = Http::timeout(30)->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                ]
            );

            if ($response->successful()) {
                $result = $response->json();
                if ($result['ok'] ?? false) {
                    Log::info('Telegram notification sent', [
                        'message_id' => $result['result']['message_id'] ?? null,
                    ]);
                    return true;
                }
            }

            Log::error('Telegram API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->error('Ошибка Telegram: ' . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error('Telegram exception: ' . $e->getMessage());
            $this->error('Исключение: ' . $e->getMessage());
            return false;
        }
    }

    private function formatMessage(array $invoices, int $totalJobs): string
    {
        $message = "🚨 *СЧЕТА НЕ ОТПРАВЛЯЮТСЯ!*\n\n";
        $message .= "⏱ В очереди более 3 дней\n";
        $message .= "📊 Всего задач: {$totalJobs}\n";
        $message .= "📋 Уникальных счетов: " . count($invoices) . "\n\n";
        $message .= "═══════════════════\n\n";

        $displayInvoices = array_slice($invoices, 0, 10);

        foreach ($displayInvoices as $inv) {

            $message .= "📄 *Счет №{$inv['invoice_number']}*\n";
            $message .= "   Период: {$inv['period']}\n";
            $message .= "   Сумма: {$inv['amount']} BYN\n\n";

            $message .= "🏢 *Клиент:*\n";
            $message .= "   УНП: `{$inv['client']['unp']}`\n";
            $message .= "   {$inv['client']['name']}\n";

            // Проверяем и выводим получателей
            $message .= "\n📧 *Получатели:*\n";

            $hasRecipients = false;

            if (isset($inv['recipients']['client'])) {
                $message .= "   👤 Клиент: `{$inv['recipients']['client']}`\n";
                $hasRecipients = true;
            }

            if (isset($inv['recipients']['buh'])) {
                $message .= "   📊 Бухгалтер: `{$inv['recipients']['buh']}`\n";
                $hasRecipients = true;
            }

            // Если получателей нет, показываем предупреждение
            if (!$hasRecipients) {
                $message .= "   ⚠️ Получатели не определены\n";
            }

            $message .= "\n⏰ В очереди с: {$inv['stuck_since']}\n";
            $message .= "───────────────────\n\n";
        }

        if (count($invoices) > 10) {
            $more = count($invoices) - 10;
            $message .= "➕ *И ещё {$more} счетов...*\n\n";
        }

        $message .= "⚠️ *Требуется срочная проверка!*";

        return $message;
    }


    private function extractIdsFromSerializedCommand(string $command): array
    {
        $result = [
            'invoice_id' => null,
            'client_id' => null,
            'is_duplicate' => false,
            'recipient_email' => null,
        ];

        try {
            // Десериализуем команду
            $job = @unserialize($command);

            if ($job === false) {
                throw new \Exception('Unserialize failed');
            }

            // Приводим к массиву для доступа к protected-свойствам
            // Ключи имеют вид "\0*\0propertyName" для protected
            $jobArray = (array) $job;

            // Извлекаем recipientEmail (protected свойство)
            $result['recipient_email'] = $jobArray["\0*\0recipientEmail"] ?? null;

            // Извлекаем isDuplicate (protected свойство)
            $result['is_duplicate'] = $jobArray["\0*\0isDuplicate"] ?? false;

            // Извлекаем client (ModelIdentifier)
            $client = $jobArray["\0*\0client"] ?? null;
            if ($client && is_object($client) && isset($client->id)) {
                $result['client_id'] = $client->id;
            }

            // Извлекаем invoice (ModelIdentifier)
            $invoice = $jobArray["\0*\0invoice"] ?? null;
            if ($invoice && is_object($invoice) && isset($invoice->id)) {
                $result['invoice_id'] = $invoice->id;
            }

        } catch (\Exception $e) {
            // Fallback на регулярки если что-то пошло не так
            Log::warning('Unserialize failed, using regex fallback: ' . $e->getMessage());

            if (preg_match('/"App\\\\Models\\\\CarwashInvoice"[^}]+s:2:"id";i:(\d+)/', $command, $matches)) {
                $result['invoice_id'] = (int)$matches[1];
            }

            if (preg_match('/"App\\\\Models\\\\CarwashClient"[^}]+s:2:"id";i:(\d+)/', $command, $matches)) {
                $result['client_id'] = (int)$matches[1];
            }

            if (preg_match('/"isDuplicate";b:(\d)/', $command, $matches)) {
                $result['is_duplicate'] = (bool)$matches[1];
            }

            // Для email используем ручной парсинг
            $search = '"recipientEmail";s:';
            $pos = strpos($command, $search);

            if ($pos !== false) {
                $lengthStart = $pos + strlen($search);
                $colonPos = strpos($command, ':', $lengthStart);
                $quotePos = strpos($command, '"', $lengthStart);

                if ($colonPos !== false && $quotePos !== false && $quotePos > $colonPos) {
                    $lengthStr = substr($command, $lengthStart, $colonPos - $lengthStart);
                    $length = (int)$lengthStr;
                    $stringStart = $quotePos + 1;
                    $result['recipient_email'] = substr($command, $stringStart, $length);
                }
            }
        }

        return $result;
    }

    private function extractInvoiceNumber(?string $filePath): string
    {
        if (!$filePath) {
            return 'N/A';
        }

        if (preg_match('/num_(\d+)\.xls$/', $filePath, $matches)) {
            return 'AM-' . $matches[1];
        }

        return 'N/A';
    }
}
