<?php

namespace App\Console\Commands;

use App\Services\StockAlertService;
use Illuminate\Console\Command;

class CheckOverdueAlerts extends Command
{
    protected $signature = 'alerts:check-overdue';

    protected $description = 'Check for overdue receivables and supplier payables and create notifications';

    public function handle(StockAlertService $alertService): int
    {
        $receivables = $alertService->checkOverdueReceivables();
        $payables = $alertService->checkOverdueSupplierPayables();

        $this->info("Created {$receivables} receivable overdue notification(s).");
        $this->info("Created {$payables} supplier overdue notification(s).");

        return self::SUCCESS;
    }
}
