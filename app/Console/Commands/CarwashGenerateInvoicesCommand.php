<?php

namespace App\Console\Commands;

use App\Models\CarwashClient;
use App\Models\CarwashInvoice;
use App\Services\CarwashInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CarwashGenerateInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'carwash:invoices-generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily command to generate and send invoices to clients based on their invoice_email_day and for the previous month.';

    /**
     * The CarwashInvoiceService instance.
     *
     * @var \App\Services\CarwashInvoiceService
     */
    protected CarwashInvoiceService $invoiceService;

    /**
     * Create a new command instance.
     *
     * @param \App\Services\CarwashInvoiceService $invoiceService
     * @return void
     */
    public function __construct(CarwashInvoiceService $invoiceService)
    {
        parent::__construct();
        $this->invoiceService = $invoiceService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        Log::info('CarwashGenerateInvoicesCommand: Starting daily invoice generation process.');

        $currentDate = Carbon::now();

        // Select active clients who require email invoices
        $clients = CarwashClient::where('status', 'active')
            ->where('invoice_email_required', true)
            ->get();

        if ($clients->isEmpty()) {
            Log::info('CarwashGenerateInvoicesCommand: No active clients require invoices today.');
            $this->info('No active clients require invoices today.');
            return Command::SUCCESS;
        }

        Log::info("CarwashGenerateInvoicesCommand: Found {$clients->count()} clients to check for invoice generation.");

        foreach ($clients as $client) {
            Log::info("CarwashGenerateInvoicesCommand: Checking client ID: {$client->id} ({$client->short_name}). Invoice email day: {$client->invoice_email_day}. Current day: {$currentDate->day}.");

            if ((int)$client->invoice_email_day <= (int)$currentDate->day) {
                Log::info("CarwashGenerateInvoicesCommand: Matched invoice_email_day for client ID: {$client->id}.");

                // Define the period for the invoice (previous month)
                // $periodDateForService is the date based on which createAndSendInvoiceForClient determines "previous month"
                // If command runs on July 5th, it should generate invoice for June.
                // So, $currentDate (July 5th) is correct to pass to the service.
                $periodDateForService = $currentDate->copy();

                $previousMonthStart = $periodDateForService->copy()->subMonthNoOverflow()->startOfMonth();
                $previousMonthEnd = $periodDateForService->copy()->subMonthNoOverflow()->endOfMonth();

                // Check if an invoice already exists for this client and the previous month
                $existingInvoice = CarwashInvoice::where('client_id', $client->id)
                    ->where('period_start', $previousMonthStart->toDateString())
                    ->where('period_end', $previousMonthEnd->toDateString())
                    ->exists();

                if ($existingInvoice) {
                    Log::info("CarwashGenerateInvoicesCommand: Invoice already exists for client ID: {$client->id} for period {$previousMonthStart->toDateString()} - {$previousMonthEnd->toDateString()}. Skipping.");
                    $this->line("Skipping client ID: {$client->id}. Invoice for previous month already exists.");
                    continue;
                }

                Log::info("CarwashGenerateInvoicesCommand: Attempting to generate invoice for client ID: {$client->id} for period {$previousMonthStart->toDateString()} - {$previousMonthEnd->toDateString()}.");
                $this->line("Processing client ID: {$client->id} ({$client->short_name}) for invoice generation.");

                try {
                    $success = $this->invoiceService->createAndSendInvoiceForClient($client, $periodDateForService);
                    if ($success) {
                        Log::info("CarwashGenerateInvoicesCommand: Successfully generated and sent invoice for client ID: {$client->id}.");
                        $this->info("Successfully processed client ID: {$client->id}.");
                    } else {
                        Log::warning("CarwashGenerateInvoicesCommand: Failed to generate invoice for client ID: {$client->id}. Service returned false.");
                        $this->error("Failed to process client ID: {$client->id}. Check logs for details.");
                    }
                } catch (\Exception $e) {
                    Log::error("CarwashGenerateInvoicesCommand: Exception caught while processing client ID: {$client->id}. Message: {$e->getMessage()}", [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->error("Error processing client ID: {$client->id}. Exception: {$e->getMessage()}");
                    // Continue to the next client
                }
            } else {
                Log::info("CarwashGenerateInvoicesCommand: Not the designated invoice day for client ID: {$client->id}. Skipping.");
            }
        }

        Log::info('CarwashGenerateInvoicesCommand: Finished daily invoice generation process.');
        $this->info('Daily invoice generation process finished.');
        return Command::SUCCESS;
    }
}
