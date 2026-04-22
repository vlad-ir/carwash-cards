<?php

namespace App\Jobs;

use App\Mail\CarwashInvoiceMail;
use App\Mail\CarwashInvoiceDuplicateMail;
use App\Models\CarwashClient;
use App\Models\CarwashInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;
use Illuminate\Mail\Transport\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendCarwashInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $backoff = 0;

    protected CarwashClient $client;
    protected CarwashInvoice $invoice;
    protected string $attachmentPath;
    protected bool $isDuplicate;
    protected string $recipientEmail;

    // Rate limit settings из config (читаем один раз при создании)
    protected int $emailsPerBatch;
    protected int $delayBetweenEmails;
    protected int $delayAfterBatch;

    /**
     * Create a new job instance.
     */
    public function __construct(
        CarwashClient $client,
        CarwashInvoice $invoice,
        string $attachmentPath,
        bool $isDuplicate = false
    ) {
        $this->client = $client;
        $this->invoice = $invoice;
        $this->attachmentPath = $attachmentPath;
        $this->isDuplicate = $isDuplicate;
        $this->recipientEmail = $isDuplicate
            ? config('mail.mail_duplicate_address', '')
            : $client->email;

        // Загружаем настройки rate limiting из config
        $this->emailsPerBatch = config('mail.yandex_rate_limit.emails_per_batch', 10);
        $this->delayBetweenEmails = config('mail.yandex_rate_limit.delay_between_emails', 60);
        $this->delayAfterBatch = config('mail.yandex_rate_limit.delay_after_batch', 3600);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Проверяем валидность данных
        if (empty($this->recipientEmail)) {
            Log::warning("Recipient email is empty. Skipping email permanently.", [
                'client_id' => $this->client->id,
                'is_duplicate' => $this->isDuplicate,
            ]);
            return;
        }

        if (!File::exists($this->attachmentPath)) {
            Log::error("Attachment file not found: {$this->attachmentPath}", [
                'client_id' => $this->client->id,
                'invoice_id' => $this->invoice->id,
            ]);
            return;
        }

        // 2. Проверяем rate limit (пачки писем)
        $rateLimitKey = 'yandex_email_rate_limit';
        $emailsSentInBatch = Cache::get($rateLimitKey, 0);
        $batchStartTime = Cache::get($rateLimitKey . '_start_time');

        if ($batchStartTime === null) {
            Cache::put($rateLimitKey . '_start_time', now()->timestamp, now()->addHours(2));
            $batchStartTime = now()->timestamp;
        }

        // Если отправили лимит писем — ждём паузу между пачками
        if ($emailsSentInBatch >= $this->emailsPerBatch) {
            $elapsedSinceBatchStart = now()->timestamp - $batchStartTime;

            if ($elapsedSinceBatchStart < $this->delayAfterBatch) {
                $waitTime = $this->delayAfterBatch - $elapsedSinceBatchStart;

                Log::info("Batch limit reached ({$this->emailsPerBatch} emails). Waiting {$waitTime}s before next batch.", [
                    'client_id' => $this->client->id,
                    'invoice_id' => $this->invoice->id,
                ]);

                $this->release($waitTime);
                return;
            }

            // Начинаем новую пачку
            Cache::put($rateLimitKey, 0, now()->addHours(2));
            Cache::put($rateLimitKey . '_start_time', now()->timestamp, now()->addHours(2));
            $emailsSentInBatch = 0;
        }

        // 3. Проверяем интервал между письмами
        $lastEmailTime = Cache::get('yandex_last_email_time');
        if ($lastEmailTime !== null) {
            $timeSinceLastEmail = now()->timestamp - $lastEmailTime;

            if ($timeSinceLastEmail < $this->delayBetweenEmails) {
                $waitTime = $this->delayBetweenEmails - $timeSinceLastEmail;

                Log::info("Rate limit: waiting {$waitTime}s between emails.", [
                    'client_id' => $this->client->id,
                    'invoice_id' => $this->invoice->id,
                ]);

                $this->release($waitTime);
                return;
            }
        }

        // 4. Отправляем письмо
        try {
            $mailClass = $this->isDuplicate
                ? CarwashInvoiceDuplicateMail::class
                : CarwashInvoiceMail::class;

            Mail::to($this->recipientEmail)->send(
                new $mailClass($this->client, $this->invoice, $this->attachmentPath)
            );

            if (!$this->isDuplicate) {
                $this->invoice->sent_at = now();
                $this->invoice->save();
            }

            // Обновляем счётчики rate limit
            Cache::increment($rateLimitKey);
            Cache::put('yandex_last_email_time', now()->timestamp, now()->addHours(2));

            Log::info("Invoice email sent successfully.", [
                'recipient' => $this->recipientEmail,
                'client_id' => $this->client->id,
                'invoice_id' => $this->invoice->id,
                'is_duplicate' => $this->isDuplicate,
                'batch_count' => $emailsSentInBatch + 1,
                'settings' => [
                    'emails_per_batch' => $this->emailsPerBatch,
                    'delay_between' => $this->delayBetweenEmails,
                    'delay_after_batch' => $this->delayAfterBatch,
                ],
            ]);

        } catch (TransportExceptionInterface | TransportException $e) {
            $errorMessage = $e->getMessage();
            $isTemporaryError = $this->isTemporaryMailError($errorMessage);

            if ($isTemporaryError) {
                Log::warning("Temporary email error (will retry in {$this->delayBetweenEmails}s): {$errorMessage}", [
                    'client_id' => $this->client->id,
                    'invoice_id' => $this->invoice->id,
                ]);

                $this->release($this->delayBetweenEmails);
                return;
            }

            Log::error("Permanent email error: {$errorMessage}", [
                'client_id' => $this->client->id,
                'invoice_id' => $this->invoice->id,
            ]);

        } catch (Exception $e) {
            Log::error("Unexpected error: {$e->getMessage()}", [
                'exception' => $e,
                'client_id' => $this->client->id,
                'invoice_id' => $this->invoice->id,
            ]);
        }
    }

    /**
     * Определяет, является ли ошибка временной
     */
    private function isTemporaryMailError(string $errorMessage): bool
    {
        $temporaryPatterns = [
            'too many requests',
            'rate limit',
            'temporarily unavailable',
            'try again',
            'timeout',
            'connection refused',
            'unable to connect',
            '4.7.1',
            '4.4.2',
            '4.7.0',
            '421',
            '450',
            '451',
        ];

        $lowerMessage = strtolower($errorMessage);

        foreach ($temporaryPatterns as $pattern) {
            if (str_contains($lowerMessage, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::critical("Invoice email job failed (fatal).", [
            'client_id' => $this->client->id,
            'invoice_id' => $this->invoice->id,
            'is_duplicate' => $this->isDuplicate,
            'exception' => $exception->getMessage(),
        ]);
    }
}
