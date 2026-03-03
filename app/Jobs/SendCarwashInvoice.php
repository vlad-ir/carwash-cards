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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Exception;

class SendCarwashInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300; // 5 минут между попытками при ошибке

    protected CarwashClient $client;
    protected CarwashInvoice $invoice;
    protected string $attachmentPath;
    protected bool $isDuplicate;
    protected string $recipientEmail;

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
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Проверяем лимит через RateLimiter
        if (RateLimiter::tooManyAttempts('invoice-emails', 1)) {
            $availableIn = RateLimiter::availableIn('invoice-emails');

            Log::warning("Invoice email rate limit exceeded. Releasing job.", [
                'client_id' => $this->client->id,
                'is_duplicate' => $this->isDuplicate,
                'available_in_seconds' => $availableIn,
            ]);

            // Освобождаем задачу с задержкой до доступного слота
            $this->release($availableIn);
            return;
        }

        // Увеличиваем счётчик попыток
        RateLimiter::hit('invoice-emails');

        // Проверяем наличие файла
        if (!File::exists($this->attachmentPath)) {
            Log::error("Attachment file not found for invoice email: {$this->attachmentPath}", [
                'client_id' => $this->client->id,
                'invoice_id' => $this->invoice->id,
            ]);
            return;
        }

        // Проверяем email
        if (empty($this->recipientEmail)) {
            Log::warning("Recipient email is empty. Skipping email.", [
                'client_id' => $this->client->id,
                'is_duplicate' => $this->isDuplicate,
            ]);
            return;
        }

        try {
            // Выбираем класс письма
            $mailClass = $this->isDuplicate
                ? CarwashInvoiceDuplicateMail::class
                : CarwashInvoiceMail::class;

            // Отправляем письмо
            Mail::to($this->recipientEmail)->send(
                new $mailClass($this->client, $this->invoice, $this->attachmentPath)
            );

            // Обновляем статус отправки только для клиентских писем
            // Используем существующее поле sent_at
            if (!$this->isDuplicate) {
                $this->invoice->sent_at = now();
                $this->invoice->save();
            }

            Log::info("Invoice email sent successfully.", [
                'recipient' => $this->recipientEmail,
                'client_id' => $this->client->id,
                'invoice_id' => $this->invoice->id,
                'is_duplicate' => $this->isDuplicate,
            ]);

        } catch (Exception $e) {
            Log::error("Failed to send invoice email: {$e->getMessage()}", [
                'exception' => $e,
                'client_id' => $this->client->id,
                'invoice_id' => $this->invoice->id,
            ]);

            // Пробрасываем исключение для retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::critical("Invoice email job failed after all retries.", [
            'client_id' => $this->client->id,
            'invoice_id' => $this->invoice->id,
            'is_duplicate' => $this->isDuplicate,
            'exception' => $exception->getMessage(),
        ]);
    }
}
