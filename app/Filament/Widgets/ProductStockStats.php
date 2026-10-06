<?php

namespace App\Filament\Widgets;

use App\Models\Master\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

use Livewire\Attributes\On;

class ProductStockStats extends BaseWidget
{
    public ?int $product_id = null;
    
    protected static ?int $sort = 1;

    public function getStats(): array
    {
        $product = Product::find($this->product_id);
        if (! $product) return [];

        $training = ForecastTraining::query()
            ->where('product_id', $this->product_id)
            ->where('is_active', true)
            ->first();

        if (! $training) return [
            'name'   => $product->name,
            'stock'  => $product->stock,
            'status' => 'Belum ada training aktif',
        ];

        $totalForecast = ForecastTrainingResult::query()
            ->where('forecast_training_id', $training->id)
            ->where('is_future', true)
            ->sum('forecast');

        $sisa   = $product->stock - $totalForecast;
        $status = $sisa < 0
            ? 'Tambah Stok (' . abs(round($sisa, 0)) . ')'
            : 'Aman';

        return [
            'name'   => $product->name,
            'stock'  => $product->stock,
            'status' => $status,
        ];
    }
    #[On('refresh-stats')]
    public function updateStats($product_id)
    {
        $this->product_id = $product_id;
    }
    
    private function getStatusText($product) { /* logic */ }
}