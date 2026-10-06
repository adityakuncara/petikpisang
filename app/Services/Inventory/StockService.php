<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Master\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Observers\SaleObserver;

/**
 * ==========================================================================
 * StockService
 * ==========================================================================
 *
 * Seluruh perubahan stok HARUS melalui class ini.
 *
 * Business Rules
 * --------------------------------------------------------------------------
 * ✓ Stok tidak boleh negatif.
 * ✓ Menggunakan lockForUpdate() untuk menghindari race condition.
 * ✓ Semua perubahan stok menggunakan Database Transaction.
 *
 * Public Method
 * --------------------------------------------------------------------------
 * increase()  -> Menambah stok
 * decrease()  -> Mengurangi stok
 * restore()   -> Mengembalikan stok
 * deduct()    -> Menyesuaikan perubahan quantity
 * transfer()  -> Memindahkan transaksi ke produk lain
 */
class StockService
{
    /**
     * ----------------------------------------------------------------------
     * Menambah stok.
     *
     * Digunakan untuk:
     * - Pembelian
     * - Koreksi Stok
     * - Produksi
     * ----------------------------------------------------------------------
     */
    public function increase(
        int $productId,
        float $quantity
    ): void {

        DB::transaction(function () use ($productId, $quantity) {

            $product = $this->getLockedProduct($productId);

            $product->increment('stock', $quantity);

        });

    }

    /**
     * ----------------------------------------------------------------------
     * Mengurangi stok.
     *
     * Digunakan untuk:
     * - Penjualan
     *
     * Stok tidak boleh menjadi negatif.
     * ----------------------------------------------------------------------
     */
    public function decrease(
        int $productId,
        float $quantity
    ): void {

        DB::transaction(function () use ($productId, $quantity) {

            $product = $this->getLockedProduct($productId);

            $this->ensureStockAvailable(
                $product,
                $quantity
            );

            $product->decrement(
                'stock',
                $quantity
            );

        });

    }

    /**
     * ----------------------------------------------------------------------
     * Mengembalikan stok.
     *
     * Digunakan untuk:
     * - Delete Sale
     * - Retur
     * ----------------------------------------------------------------------
     */
    public function restore(
        int $productId,
        float $quantity
    ): void {

        DB::transaction(function () use ($productId, $quantity) {

            $product = $this->getLockedProduct($productId);

            $product->increment(
                'stock',
                $quantity
            );

        });

    }

    /**
     * ----------------------------------------------------------------------
     * Menyesuaikan perubahan quantity.
     *
     * Contoh:
     *
     * Lama : 100
     * Baru : 95
     *
     * maka stok bertambah 5
     *
     * -----------------------
     *
     * Lama : 100
     * Baru : 120
     *
     * maka stok berkurang 20
     * ----------------------------------------------------------------------
     */
    public function deduct(
        int $productId,
        float $oldQuantity,
        float $newQuantity
    ): void {

        DB::transaction(function () use (

            $productId,
            $oldQuantity,
            $newQuantity

        ) {

            $difference = $newQuantity - $oldQuantity;

            /**
             * Quantity bertambah
             */
            if ($difference > 0) {

                $this->decrease(
                    $productId,
                    $difference
                );

                return;

            }

            /**
             * Quantity berkurang
             */
            if ($difference < 0) {

                $this->restore(
                    $productId,
                    abs($difference)
                );

            }

        });

    }

    /**
     * ----------------------------------------------------------------------
     * Transfer Produk
     *
     * Digunakan ketika produk pada transaksi diubah.
     *
     * Contoh:
     *
     * Produk A (100)
     *
     * menjadi
     *
     * Produk B (95)
     *
     * Maka:
     *
     * Produk A +100
     * Produk B -95
     * ----------------------------------------------------------------------
     */
    public function transfer(
        int $oldProductId,
        float $oldQuantity,
        int $newProductId,
        float $newQuantity
    ): void {

        DB::transaction(function () use (

            $oldProductId,
            $oldQuantity,
            $newProductId,
            $newQuantity

        ) {

            /**
             * Kembalikan stok produk lama.
             */
            $this->restore(
                $oldProductId,
                $oldQuantity
            );

            /**
             * Kurangi stok produk baru.
             */
            $this->decrease(
                $newProductId,
                $newQuantity
            );

        });

    }

    /**
     * ----------------------------------------------------------------------
     * Mengambil product dengan lock.
     *
     * lockForUpdate() mencegah race condition
     * ketika dua user melakukan transaksi bersamaan.
     * ----------------------------------------------------------------------
     */
    private function getLockedProduct(
        int $productId
    ): Product {

        return Product::query()

            ->lockForUpdate()

            ->findOrFail($productId);

    }

    /**
     * ----------------------------------------------------------------------
     * Validasi stok.
     *
     * Stok tidak boleh negatif.
     * ----------------------------------------------------------------------
     */
    private function ensureStockAvailable(
        Product $product,
        float $quantity
    ): void {

        if ($product->stock < $quantity) {

            throw ValidationException::withMessages([

                'quantity' => sprintf(

                    'Stok "%s" tidak mencukupi. Tersedia %.3f.',

                    $product->name,

                    $product->stock

                ),

            ]);

        }

    }

}
