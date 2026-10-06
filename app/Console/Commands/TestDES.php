<?php

namespace App\Console\Commands;

use App\Enums\GranularityEnum;
use App\Models\Master\Product;
use App\Models\Transaksi\Sale;
use App\Services\Forecast\DESHoltService;
use App\Services\Forecast\ForecastService;
use App\Support\Aggregator\SalesAggregator;
use App\Support\Metrics\ForecastMetric;
use Illuminate\Console\Command;

class TestDES extends Command
{
    protected $signature = 'forecast:test-des
                            {product : Product ID}
                            {--alpha=0.10 : Alpha}
                            {--beta=0.80 : Beta}
                            {--granularity=monthly : weekly|monthly}
                            {--future=5 : Future Forecast}';
    
    protected $description = 'Testing Engine Double Exponential Smoothing (Holt)';

    public function handle(): int
    {
        $productId = (int) $this->argument('product');
        $alpha = (float) $this->option('alpha');
        $beta = (float) $this->option('beta');
        $future = (int) $this->option('future');
        $granularity = match (strtolower($this->option('granularity'))) {
            'weekly' => GranularityEnum::WEEKLY,
            default => GranularityEnum::MONTHLY,
        };

        $product = Product::find($productId);
        if (!$product) {
            $this->error('Produk tidak ditemukan.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info(str_repeat('=', 75));
        $this->info('      DOUBLE EXPONENTIAL SMOOTHING (HOLT) - REPORT CALCULATION      ');
        $this->info(str_repeat('=', 75));
        $this->line('Produk        : '.$product->name);
        $this->line('Granularity   : '.$granularity->value);
        $this->line('Alpha         : '.$alpha);
        $this->line('Beta          : '.$beta);
        $this->line('Horizon/Future: '.$future . ' periode ke depan');
        $this->info(str_repeat('-', 75));

        // 1. Ambil & Agregasikan data transaksi
        $sales = Sale::query()
            ->where('product_id', $productId)
            ->orderBy('sale_date')
            ->get();

        $aggregator = new SalesAggregator();
        $dataset = $aggregator->aggregate(sales: $sales, granularity: $granularity);

        if ($dataset->count() < 2) {
            $this->error('Gagal: Dataset minimal terdiri dari 2 periode untuk inisialisasi.');
            return self::FAILURE;
        }

        // 2. Jalankan Proses Training DES Holt
        $desService = new DESHoltService($alpha, $beta);
        $trainingResult = $desService->train($dataset, $granularity);

        // 3. Jalankan Proses Prediksi Masa Depan (Future Forecast)
        $futureResult = $desService->predict($future);

        // 4. Hitung Metrik Evaluasi Error (Mulai dari indeks ke-2 / setelah inisialisasi)
        $metricService = new ForecastMetric();
        $metrics = $metricService->calculate($trainingResult);

        // 5. TAMPILKAN TABEL RINCIAN PERHITUNGAN IN-SAMPLE (TRAINING)
        $this->info("\n[1] TABEL HASIL PERHITUNGAN TRAINING (IN-SAMPLE)");
        $this->table(
            [
                'No',
                'Periode Date',
                'Actual (Yt)',
                'Level (Lt)',
                'Trend (Tt)',
                'Forecast (Ft)',
                'Error (et)',
                'Abs Error',
                'Sq Error',
                'APE (%)'
            ],
            $trainingResult->map(function ($row) use ($granularity) {
                // Konversi label periode tampilan
                $periodLabel = $granularity === GranularityEnum::MONTHLY 
                    ? $row['period_date']->format('Y-m') 
                    : sprintf('%d-W%02d', $row['period_date']->isoWeekYear(), $row['period_date']->isoWeek());

                return [
                    $row['index'],
                    $periodLabel,
                    $row['actual'],
                    $row['level'],
                    $row['trend'],
                    $row['forecast'] ?? '-',
                    $row['error'] ?? '-',
                    $row['absolute_error'] ?? '-',
                    $row['squared_error'] ?? '-',
                    $row['ape'] !== null ? $row['ape'] . '%' : '-',
                ];
            })
        );

        // 6. TAMPILKAN TABEL PREDIKSI MASA DEPAN (OUT-OF-SAMPLE)
        $this->info("\n[2] TABEL HASIL PREDIKSI MASA DEPAN (OUT-OF-SAMPLE)");
        $this->table(
            [
                'No',
                'Periode Date',
                'Forecast (Ft+m)',
                'Status'
            ],
            $futureResult->map(function ($row) use ($granularity) {
                $periodLabel = $granularity === GranularityEnum::MONTHLY 
                    ? $row['period_date']->format('Y-m') 
                    : sprintf('%d-W%02d', $row['period_date']->isoWeekYear(), $row['period_date']->isoWeek());

                return [
                    $row['index'],
                    $periodLabel,
                    $row['forecast'],
                    '🔮 Prediksi Masa Depan'
                ];
            })
        );

        // 7. TAMPILKAN RINGKASAN METRIK EVALUASI AKURASI MODEL
        $this->info("\n[3] RINGKASAN AKURASI MODEL (EVALUASI ERROR)");
        $this->info(str_repeat('-', 45));
        $this->line(sprintf(" Mean Absolute Error (MAE)            : %s", $metrics['mae']));
        $this->line(sprintf(" Mean Absolute Percentage Error (MAPE): %s%%", $metrics['mape']));
        $this->line(sprintf(" Root Mean Squared Error (RMSE)       : %s", $metrics['rmse']));
        $this->line(sprintf(" Sum Squared Error (SSE)              : %s", $metrics['sse']));
        $this->info(str_repeat('=', 45));
        $this->newLine();

        
        $forecast = new ForecastService();
        $result = $forecast->run(
            product:$product,
            alpha:$alpha,
            beta:$beta,
            granularity:$granularity,
            future:$future
        );
        return self::SUCCESS;
    }
}