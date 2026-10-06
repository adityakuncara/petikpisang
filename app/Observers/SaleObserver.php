<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Transaksi\Sale;
use App\Services\Inventory\StockService;

class SaleObserver
{
    // Tambahkan constructor untuk inject StockService
    public function __construct(
        protected StockService $stock
    ) {}

    public function created(Sale $sale): void
    {
        $this->stock->decrease($sale->product_id, (float) $sale->quantity);
    }

    public function updated(Sale $sale): void
    {
        $productChanged  = $sale->wasChanged('product_id');
        $quantityChanged = $sale->wasChanged('quantity');

        if ($productChanged) {
            // Produk berganti → kembalikan stok lama, kurangi stok produk baru (Sesuai Poin 3)
            $this->stock->transfer(
                oldProductId: (int) $sale->getOriginal('product_id'),
                oldQuantity:  (float) $sale->getOriginal('quantity'),
                newProductId: (int) $sale->product_id,
                newQuantity:  (float) $sale->quantity,
            );
        } elseif ($quantityChanged) {
            // Produk sama, hanya quantity berubah (Sesuai Poin 1 & 2)
            $this->stock->deduct(
                productId:   (int) $sale->product_id,
                oldQuantity: (float) $sale->getOriginal('quantity'),
                newQuantity: (float) $sale->quantity,
            );
        }
    }

    public function deleted(Sale $sale): void
    {
        $this->stock->restore($sale->product_id, (float) $sale->quantity);
    }
}