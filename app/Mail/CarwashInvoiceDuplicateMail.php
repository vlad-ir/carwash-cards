<?php

namespace App\Mail;

use App\Models\CarwashClient;
use App\Models\CarwashInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class CarwashInvoiceDuplicateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public CarwashClient $client;
    public CarwashInvoice $invoice;
    public string $attachmentPath;

    /**
     * Create a new message instance.
     */
    public function __construct(CarwashClient $client, CarwashInvoice $invoice, string $attachmentPath)
    {
        $this->client = $client;
        $this->invoice = $invoice;
        $this->attachmentPath = $attachmentPath;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Копия счета на оплату услуг автомойки (для бухгалтерии)',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'carwash_emails.duplicateinvoice',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (!File::exists($this->attachmentPath)) {
            Log::error("Attachment file not found for duplicate invoice email: {$this->attachmentPath}");
            return [];
        }

        return [
            Attachment::fromPath($this->attachmentPath)
                ->as(basename($this->attachmentPath))
                ->withMime('application/vnd.ms-excel'),
        ];
    }
}
