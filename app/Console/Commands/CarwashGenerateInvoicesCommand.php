<?php

namespace App\Console\Commands;

use App\Services\CarwashInvoiceService;
use Illuminate\Console\Command;

class CarwashGenerateInvoicesCommand extends Command
{
    protected $signature = 'carwash:invoices-generate';
    protected $description = 'Автоматическое выставление счетов по активным бонусным картам действующим клиентам';

    protected $invoiceService;

    public function __construct(CarwashInvoiceService $invoiceService)
    {
        parent::__construct();
        $this->invoiceService = $invoiceService;
    }

    public function handle()
    {
        $this->invoiceService->generateAutomaticInvoices();
        $this->info('Счета успешно созданы.');
    }
}
