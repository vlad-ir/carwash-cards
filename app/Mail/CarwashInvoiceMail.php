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
use Illuminate\Support\Facades\File; // Added this line

class CarwashInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public CarwashClient $client;
    public CarwashInvoice $invoice;
    public string $attachmentPath;

    /**
     * Create a new message instance.
     *
     * @param CarwashClient $client
     * @param CarwashInvoice $invoice
     * @param string $attachmentPath
     */
    public function __construct(CarwashClient $client, CarwashInvoice $invoice, string $attachmentPath)
    {
        $this->client = $client;
        $this->invoice = $invoice;
        $this->attachmentPath = $attachmentPath;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Счет на оплату услуг автомойки',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.carwash_invoice', // Blade view for the email body
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (!File::exists($this->attachmentPath)) { // Corrected to use File facade
            Log::error("Attachment file not found for invoice email: {$this->attachmentPath}");
            return [];
        }

        return [
            Attachment::fromPath($this->attachmentPath)
                ->as(basename($this->attachmentPath))
                ->withMime('application/vnd.ms-excel'),
        ];
    }
}
