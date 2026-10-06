<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GranularityEnum;
use App\Models\Master\Product;
use App\Services\Forecast\TrainingService;
use Illuminate\Console\Command;

class TestGridSearch extends Command
{
    /**
     * Nama Command.
     */
    protected $signature = 'forecast:test-grid
                            {product : Product ID}
                            {--granularity=monthly}';

    /**
     * Deskripsi.
     */
    protected $description = 'Testing Forecast Training Pipeline with Grid Search Optimization';

    /**
     * Handle.
     */
    public function handle(): int
    {
        /**
         * ----------------------------------------------------------
         * Product
         * ----------------------------------------------------------
         */
        $product = Product::find((int) $this->argument('product'));

        if (! $product) {
            $this->error('Produk tidak ditemukan.');
            return self::FAILURE;
        }

        /**
         * ----------------------------------------------------------
         * Granularity
         * ----------------------------------------------------------
         */
        $granularity = match (strtolower($this->option('granularity'))) {
            'weekly' => GranularityEnum::WEEKLY,
            default => GranularityEnum::MONTHLY,
        };

        $this->newLine();
        $this->info(str_repeat('=', 70));
        $this->info('        GRID SEARCH OPTIMIZATION - DOUBLE EXPONENTIAL SMOOTHING        ');
        $this->info(str_repeat('=', 70));
        $this->line('Produk      : '.$product->name);
        $this->line('Granularity : '.$granularity->value);
        $this->info(str_repeat('-', 70));

        /**
         * ----------------------------------------------------------
         * Jalankan Training & Tuning Hyperparameter
         * ----------------------------------------------------------
         */
        $this->comment('Sedang memproses kombinasi parameter (Grid Search)...');
        $service = new TrainingService();

        // Dioptimasi menggunakan metrik 'mape' dan step yang lebih presisi (0.05)
        $training = $service->train(
            product: $product,
            granularity: $granularity,
            trainingRatio: 80,
            testingRatio: 20,
            alphaStart: 0.05,
            alphaEnd: 0.95,
            betaStart: 0.05,
            betaEnd: 0.95,
            step: 0.05,
            future: 3,
            metric: 'mape'
        );

        /**
         * ----------------------------------------------------------
         * [1] Ringkasan Model Terbaik (Best Model Summary)
         * ----------------------------------------------------------
         */
        $this->newLine();
        $this->info('[1] RINGKASAN PARAMETER TERBAIK (TERPILIH)');
        $this->table(
            ['Metrik / Field', 'Value'],
            [
                ['Training ID', $training->id],
                ['Optimal Alpha (α)', $training->alpha],
                ['Optimal Beta (β)', $training->beta],
                ['Testing MAE', $training->mae],
                ['Testing MAPE', $training->mape . '%'],
                ['Testing RMSE', $training->rmse],
                ['Testing SSE', $training->sse],
                ['Total Kombinasi Diuji', $training->grid_search_total],
                ['Peringkat Grid Search', 'Rank #' . $training->best_rank],
                ['Status Training', strtoupper($training->status)],
                ['Model Aktif (Production)', $training->is_active ? '✅ YES' : '❌ NO'],
            ]
        );

        /**
         * ----------------------------------------------------------
         * [2] Top 5 Parameter Alternatif Berdasarkan MAPE Terkecil
         * ----------------------------------------------------------
         */
        $this->newLine();
        $this->info('[2] TOP 5 KANDIDAT PARAMETER TERBAIK (HASIL GRID SEARCH)');
        
        $topGrid = $training->gridSearches()
            ->orderBy('rank')
            ->take(5)
            ->get();

        $this->table(
            ['Rank', 'Alpha (α)', 'Beta (β)', 'MAPE (%)', 'MAE', 'RMSE', 'Is Best?'],
            $topGrid->map(function ($grid) {
                return [
                    '#' . $grid->rank,
                    $grid->alpha,
                    $grid->beta,
                    $grid->mape . '%',
                    $grid->mae,
                    $grid->rmse,
                    $grid->is_best ? '⭐ Terpilih' : '-',
                ];
            })->toArray()
        );

        /**
         * ----------------------------------------------------------
         * [3] Rincian Hasil Peramalan Masa Depan (Production Forecast)
         * ----------------------------------------------------------
         */
        $this->newLine();
        $this->info('[3] HASIL PERAMALAN MASA DEPAN (OUT-OF-SAMPLE FORECAST)');
        
        $futureForecasts = $training->results()
            ->where('is_future', true)
            ->orderBy('sequence')
            ->get();

        $this->table(
            ['No', 'Periode Tanggal', 'Hasil Prediksi Peramalan', 'Status'],
            $futureForecasts->map(function ($res) use ($granularity) {
                $dateLabel = $granularity === GranularityEnum::MONTHLY 
                    ? $res->period_date->format('Y-m') 
                    : sprintf('%d-W%02d', $res->period_date->isoWeekYear(), $res->period_date->isoWeek());

                return [
                    $res->sequence,
                    $dateLabel,
                    round((float) $res->forecast, 2),
                    '🔮 Future Horizon'
                ];
            })->toArray()
        );

        $this->newLine();
        return self::SUCCESS;
    }
}