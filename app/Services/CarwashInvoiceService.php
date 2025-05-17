<?php

namespace App\Services;

use App\Models\CarwashClient;
use App\Models\CarwashInvoice;
use App\Mail\InvoiceMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CarwashInvoiceService
{
    public function createInvoice($clientId, $periodStart, $periodEnd)
    {
        $client = CarwashClient::with(['bonusCards' => function ($query) {
            $query->where('status', 'active');
        }])->findOrFail($clientId);

        $periodStart = Carbon::parse($periodStart);
        $periodEnd = Carbon::parse($periodEnd);

        // Расчет суммы и минут по картам
        $details = [];
        $totalAmount = 0;

        foreach ($client->bonusCards as $card) {
            $stats = $card->stats()
                ->whereBetween('start_time', [$periodStart, $periodEnd])
                ->get();

            $totalSeconds = $stats->sum('duration_seconds');
            $minutes = ceil($totalSeconds / 60); // Округление секунд до минут вверх
            $amount = $minutes * $card->tariff;

            $details[] = [
                'card_number' => $card->card_number,
                'minutes' => $minutes,
                'tariff' => $card->tariff,
                'amount' => $amount,
            ];

            $totalAmount += $amount;
        }

        // Создание счета
        $invoice = CarwashInvoice::create([
            'client_id' => $client->id,
            'amount' => $totalAmount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        // Генерация PDF
        $pdfPath = $this->generatePdf($invoice, $client, $details);
        $invoice->update(['pdf_path' => $pdfPath]);

        // Отправка по email, если требуется
        if ($client->invoice_email_required) {
            $this->sendInvoiceEmail($invoice, $client, $pdfPath);
        }

        return $invoice;
    }

    public function generatePdf($invoice, $client, $details)
    {
        $pdf = Pdf::loadView('carwash_invoices.invoice_pdf', compact('invoice', 'client', 'details'));
        $pdfPath = 'invoices/invoice_' . $invoice->id . '.pdf';
        Storage::put($pdfPath, $pdf->output());
        return $pdfPath;
    }

    public function sendInvoiceEmail($invoice, $client, $pdfPath)
    {
        Mail::to($client->email)->send(new InvoiceMail($invoice, $pdfPath));
        $invoice->update(['sent_at' => now()]);
    }

    public function generateAutomaticInvoices()
    {
        $today = now()->toDateString();
        $clients = CarwashClient::where('status', 'active')
            ->where('invoice_required', true)
            ->where('invoice_email_date', $today)
            ->get();

        foreach ($clients as $client) {
            $periodStart = now()->subMonth()->startOfMonth();
            $periodEnd = now()->subMonth()->endOfMonth();
            $this->createInvoice($client->id, $periodStart, $periodEnd);
        }
    }
}
