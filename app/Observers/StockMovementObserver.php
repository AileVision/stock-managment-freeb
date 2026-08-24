<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Services\StockAlertService;

class StockMovementObserver
{
    public function __construct(
        private StockAlertService $alertService,
    ) {}

    /**
     * After a stock movement is created, check the product's stock level.
     */
    public function created(StockMovement $movement): void
    {
        $movement->load('product');

        if ($movement->product) {
            $this->alertService->checkProduct($movement->product);
        }
    }
}
