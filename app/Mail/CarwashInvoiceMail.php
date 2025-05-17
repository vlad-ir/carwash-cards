<?php

namespace App\Mail;

use App\Models\CarwashInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CarwashInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invoice;
    public $pdfPath;

    public function __construct(CarwashInvoice $invoice, $pdfPath)
    {
        $this->invoice = $invoice;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        return $this->subject('Счет #' . $this->invoice->id)
            ->view('carwash_emails.invoice')
            ->attach(storage_path('app/' . $this->pdfPath), [
                'as' => 'invoice_' . $this->invoice->id . '.pdf',
                'mime' => 'application/pdf',
            ]);
    }
}
