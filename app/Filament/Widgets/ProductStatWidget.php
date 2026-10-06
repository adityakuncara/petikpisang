<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Master\Product;
use App\Models\Master\ProductCategory;
use App\Models\Transaksi\Sale;
use Filament\Widgets\Widget;

/**
 * Section 1 — Statistik Master Data
 *
 * Card 1 : Jumlah Produk Aktif
 * Card 2 : Jumlah Kategori
 * Card 3 : Total Penjualan (semua transaksi)
 */
class ProductStatWidget extends Widget
{
    protected string $view = 'filament.widgets.product-stat-widget';


    // Lebar widget: full width
    protected int | string | array $columnSpan = 'full';

    // Urutan di dashboard
    protected static ?int $sort = 1;

    public function getStats(): array
    {
        $totalProduk    = Product::where('is_active', true)->count();
        $totalKategori  = ProductCategory::count();
        $totalPenjualan = (int) Sale::count();
        $totalRevenue   = (float) Sale::sum('total_revenue');
        $totalQty       = (float) Sale::sum('quantity');

        return [
            [
                'label'   => 'Produk Aktif',
                'value'   => number_format((float) $totalProduk, 0, ',', '.'),
                'sub'     => 'dari ' . Product::count() . ' total produk',
                'icon'    => '📦',
                'color'   => '#3b82f6',
                'iconBg'  => '#eff6ff',
            ],
            [
                'label'   => 'Kategori Produk',
                'value'   => number_format( (float)$totalKategori, 0, ',', '.'),
                'sub'     => 'kategori terdaftar',
                'icon'    => '🏷️',
                'color'   => '#8b5cf6',
                'iconBg'  => '#f5f3ff',
            ],
            [
                'label'   => 'Total Transaksi',
                'value'   => number_format((float) $totalPenjualan, 0, ',', '.'),
                'sub'     => number_format($totalQty, 0, ',', '.') . ' unit · Rp ' . number_format($totalRevenue, 0, ',', '.'),
                'icon'    => '🛒',
                'color'   => '#10b981',
                'iconBg'  => '#ecfdf5',
            ],
        ];
    }
}
