<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Transaksi\Sale;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Section 2 — Ringkasan Penjualan 3 Tahun Terakhir
 *
 * Card 1 : Produk Terlaris (qty tertinggi)
 * Card 2 : Produk Paling Sepi (qty terendah)
 * Card 3 : Pendapatan Tertinggi (bulan dengan total_revenue terbesar)
 */
class SalesSummaryWidget extends Widget
{
    protected string $view = 'filament.widgets.sales-summary-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function getStats(): array
    {
        $cutoff = now()->subYears(3)->startOfMonth();

        $base = Sale::query()
            ->whereDate('sale_date', '>=', $cutoff);

        // ── Card 1: Produk Terlaris ───────────────────────────────
        $terlaris = (clone $base)
            ->selectRaw('product_id, SUM(quantity) as total_qty, SUM(total_revenue) as total_rev')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->with('product')
            ->first();

        // ── Card 2: Produk Paling Sepi ────────────────────────────
        // Hanya produk yang pernah ada transaksi dalam 3 tahun terakhir
        $tersepi = (clone $base)
            ->selectRaw('product_id, SUM(quantity) as total_qty, SUM(total_revenue) as total_rev')
            ->groupBy('product_id')
            ->orderBy('total_qty')
            ->with('product')
            ->first();

        // ── Card 3: Bulan dengan Pendapatan Tertinggi ─────────────
        $pendapatanTertinggi = (clone $base)
            ->selectRaw('
                YEAR(sale_date)  as year,
                MONTH(sale_date) as month,
                SUM(total_revenue) as total_rev,
                SUM(quantity)      as total_qty,
                COUNT(*)           as total_transaksi
            ')
            ->groupBy('year', 'month')
            ->orderByDesc('total_rev')
            ->first();

        $bulanTertinggi = $pendapatanTertinggi
            ? Carbon::createFromDate(
                $pendapatanTertinggi->year,
                $pendapatanTertinggi->month,
                1
              )->translatedFormat('F Y')
            : '-';

        return [
            // Card 1 — Terlaris
            [
                'label'   => 'Produk Terlaris',
                'sub'     => '3 tahun terakhir',
                'title'   => $terlaris?->product?->name ?? '-',
                'value'   => $terlaris ? number_format((float) $terlaris->total_qty, 0, ',', '.') . ' unit' : '-',
                'revenue' => $terlaris ? 'Rp ' . number_format((float) $terlaris->total_rev, 0, ',', '.') : '-',
                'icon'    => '🏆',
                'color'   => '#15803d',
                'iconBg'  => '#dcfce7',
                'border'  => '#22c55e',
            ],

            // Card 2 — Paling Sepi
            [
                'label'   => 'Produk Paling Sepi',
                'sub'     => '3 tahun terakhir',
                'title'   => $tersepi?->product?->name ?? '-',
                'value'   => $tersepi ? number_format((float) $tersepi->total_qty, 0, ',', '.') . ' unit' : '-',
                'revenue' => $tersepi ? 'Rp ' . number_format((float) $tersepi->total_rev, 0, ',', '.') : '-',
                'icon'    => '📉',
                'color'   => '#b91c1c',
                'iconBg'  => '#fee2e2',
                'border'  => '#ef4444',
            ],

            // Card 3 — Pendapatan Tertinggi
            [
                'label'   => 'Pendapatan Tertinggi',
                'sub'     => $bulanTertinggi,
                'title'   => $pendapatanTertinggi
                    ? 'Rp ' . number_format((float) $pendapatanTertinggi->total_rev, 0, ',', '.')
                    : '-',
                'value'   => $pendapatanTertinggi
                    ? number_format((float) $pendapatanTertinggi->total_qty, 0, ',', '.') . ' unit terjual'
                    : '-',
                'revenue' => $pendapatanTertinggi
                    ? number_format((float) $pendapatanTertinggi->total_transaksi, 0, ',', '.') . ' transaksi'
                    : '-',
                'icon'    => '💰',
                'color'   => '#d97706',
                'iconBg'  => '#fef3c7',
                'border'  => '#f59e0b',
            ],
        ];
    }
}
