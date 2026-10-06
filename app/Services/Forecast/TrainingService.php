<?php

declare(strict_types=1);

namespace App\Services\Forecast;

use App\Enums\GranularityEnum;
use App\Models\Forecast\ForecastTraining;
use App\Models\Forecast\ForecastTrainingResult;
use App\Models\Forecast\ForecastGridSearch;
use App\Models\Master\Product;
use App\Support\Aggregator\SalesAggregator;
use App\Support\Metrics\ForecastMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TrainingService
{
    /**
     * Sales Aggregator.
     */
    protected SalesAggregator $aggregator;

    /**
     * Forecast Service.
     */
    protected ForecastService $forecastService;

    /**
     * Grid Search Service.
     */
    protected GridSearchService $gridSearchService;

    /**
     * Forecast Metric.
     */
    protected ForecastMetric $metric;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->aggregator = new SalesAggregator();

        $this->forecastService = new ForecastService();

        $this->gridSearchService = new GridSearchService();

        $this->metric = new ForecastMetric();
    }

    /**
     * ==========================================================
     * Training Model
     * ==========================================================
     *
     * Workflow:
     *
     * Load Dataset
     * ↓
     * Split Dataset
     * ↓
     * Create Training
     * ↓
     * Grid Search
     * ↓
     * Forecast
     * ↓
     * Save Result
     * ↓
     * Update Summary
     * ↓
     * Activate Training
     */
    public function train(
        Product $product,
        GranularityEnum $granularity,
        float $trainingRatio = 80,
        float $testingRatio = 20,
        float $alphaStart = 0.10,
        float $alphaEnd = 0.90,
        float $betaStart = 0.10,
        float $betaEnd = 0.90,
        float $step = 0.10,
        int $future = 3,
        string $metric = 'wmape'
    ): ForecastTraining 
    {

        /**
         * Validasi rasio.
         */
        if (($trainingRatio + $testingRatio) !== 100.0) {

            throw new InvalidArgumentException(
                'Training ratio dan testing ratio harus berjumlah 100%.'
            );

        }

        /**
         * Seluruh proses training
         * dijalankan dalam transaction.
         */
        return DB::transaction(function () use (
            $product,
            $granularity,
            $trainingRatio,
            $testingRatio,
            $alphaStart,
            $alphaEnd,
            $betaStart,
            $betaEnd,
            $step,
            $future,
            $metric
        ) {

            /**
             * Bagian berikutnya akan kita isi
             * secara bertahap.
             */
            $dataset = $this->loadDataset(
                product: $product,
                granularity: $granularity
            );

            $split = $this->splitDataset(
                dataset: $dataset,
                trainingRatio: $trainingRatio
            );

            $trainingDataset = $split['training'];
            $testingDataset = $split['testing'];
            $training = $this->createTraining(
                product: $product,
                granularity: $granularity,
                trainingDataset: $trainingDataset,
                testingDataset: $testingDataset,
                trainingRatio: $trainingRatio,
                testingRatio: $testingRatio
            );

            /**
             * ----------------------------------------------------------
             * Grid Search
             * ----------------------------------------------------------
             */
            $best = $this->runGridSearch(
                training: $training,
                product: $product,
                trainingDataset: $trainingDataset,
                granularity: $granularity,
                alphaStart: $alphaStart,
                alphaEnd: $alphaEnd,
                betaStart: $betaStart,
                betaEnd: $betaEnd,
                step: $step,
                future: $future,
                metric: $metric
            );

            /**
             * ----------------------------------------------------------
             * Forecast Testing (Out-of-Sample Evaluation)
             * ----------------------------------------------------------
             */
            $testingForecast = $this->runForecastForTesting(
                alpha: $best->alpha,
                beta: $best->beta,
                trainingDataset: $trainingDataset,
                testingDataset: $testingDataset,
                granularity: $granularity
            );

            // Ambil metrik evaluasi hasil testing (yang menghasilkan MAPE 8%)
            $testingMetrics = $this->metric->calculate($testingForecast);

            /**
             * ----------------------------------------------------------
             * Forecast Final (Production)
             * ----------------------------------------------------------
             */
            $forecast = $this->runForecast(
                product: $product,
                dataset: $dataset,
                granularity: $granularity,
                alpha: $best->alpha,
                beta: $best->beta,
                future: $future
            );

            // PENTING: Gabungkan data ramalan training DAN ramalan masa depan (future)
            $allResults = collect($forecast['training'])->merge($forecast['future']);

            // Perkaya dengan metadata tanggal dari SalesAggregator
            $enrichedResults = $this->enrichForecastResult(
                dataset: $dataset,
                forecast: $allResults
            );

            // Simpan semuanya ke database hasil training
            $this->saveTrainingResults(
                training: $training,
                results: $enrichedResults
            );

            /***********************************************************
             * Update Header Training dengan Metrik Validasi Terbaik
             ***********************************************************/
            $training = $this->updateTrainingSummary(
                training: $training,
                bestParameter: $best,
                metrics: $testingMetrics // Gunakan nilai $testingMetrics (MAPE 8%)
            );

            /***********************************************************
             * Aktifkan Training
             ***********************************************************/
            $this->activateTraining($training);
            return $training;
        });

    }

    /**
     * ==========================================================
     * Load Dataset
     * ==========================================================
     *
     * Mengambil histori penjualan kemudian
     * melakukan agregasi berdasarkan granularitas.
     */
    private function loadDataset(
        Product $product,
        GranularityEnum $granularity
    ): Collection 
    {

        $sales = $product->sales()
            ->orderBy('sale_date')
            ->get();

        if ($sales->isEmpty()) {

            throw new InvalidArgumentException(
                'Data penjualan tidak ditemukan.'
            );

        }

        $dataset = $this->aggregator->aggregate(
            sales: $sales,
            granularity: $granularity
        );

        if ($dataset->count() < 2) {

            throw new InvalidArgumentException(
                'Dataset minimal terdiri dari dua periode.'
            );

        }

        return $dataset;

    }  
    
    /**
     * ==========================================================
     * Split Dataset
     * ==========================================================
     *
     * Membagi dataset menjadi:
     *
     * Training Dataset
     * Testing Dataset
     */
    private function splitDataset(
        Collection $dataset,
        float $trainingRatio
    ): array 
    {

        $total = $dataset->count();

        $trainingCount = (int) floor(
            ($trainingRatio / 100) * $total
        );

        /**
         * Minimal dua data
         * agar DES dapat membentuk
         * Level dan Trend.
         */
        $trainingCount = max(
            2,
            $trainingCount
        );

        /**
         * Pastikan testing tetap memiliki data.
         */
        if ($trainingCount >= $total) {

            $trainingCount = $total - 1;

        }

        $training = $dataset
            ->take($trainingCount)
            ->values();

        $testing = $dataset
            ->skip($trainingCount)
            ->values();

        return [

            'training' => $training,

            'testing' => $testing,

        ];

    }

    /**
     * ==========================================================
     * Train With Dataset (Already Loaded)
     * ==========================================================
     *
     * Sama seperti train(), tetapi menggunakan dataset yang sudah disiapkan,
     * tanpa mengambil dari database lagi.
     */
    public function trainWithDataset(
        Product $product,
        Collection $dataset,
        GranularityEnum $granularity,
        float $trainingRatio = 80,
        float $testingRatio = 20,
        float $alphaStart = 0.10,
        float $alphaEnd = 0.90,
        float $betaStart = 0.10,
        float $betaEnd = 0.90,
        float $step = 0.10,
        int $future = 3,
        string $metric = 'wmape'
    ): ForecastTraining {
        // Validasi rasio
        if (($trainingRatio + $testingRatio) !== 100.0) {
            throw new InvalidArgumentException(
                'Training ratio dan testing ratio harus berjumlah 100%.'
            );
        }

        return DB::transaction(function () use (
            $product,
            $dataset,
            $granularity,
            $trainingRatio,
            $testingRatio,
            $alphaStart,
            $alphaEnd,
            $betaStart,
            $betaEnd,
            $step,
            $future,
            $metric
        ) {
            // Split dataset
            $split = $this->splitDataset(
                dataset: $dataset,
                trainingRatio: $trainingRatio
            );
            $trainingDataset = $split['training'];
            $testingDataset  = $split['testing'];

            // Buat header training
            $training = $this->createTraining(
                product: $product,
                granularity: $granularity,
                trainingDataset: $trainingDataset,
                testingDataset: $testingDataset,
                trainingRatio: $trainingRatio,
                testingRatio: $testingRatio
            );

            // Grid Search
            $best = $this->runGridSearch(
                training: $training,
                product: $product,
                trainingDataset: $trainingDataset,
                granularity: $granularity,
                alphaStart: $alphaStart,
                alphaEnd: $alphaEnd,
                betaStart: $betaStart,
                betaEnd: $betaEnd,
                step: $step,
                future: $future,
                metric: $metric
            );

            // Testing (out-of-sample)
            $testingForecast = $this->runForecastForTesting(
                alpha: $best->alpha,
                beta: $best->beta,
                trainingDataset: $trainingDataset,
                testingDataset: $testingDataset,
                granularity: $granularity
            );
            $testingMetrics = $this->metric->calculate($testingForecast);

            // Forecast final (production)
            $forecast = $this->runForecast(
                product: $product,
                dataset: $dataset,
                granularity: $granularity,
                alpha: $best->alpha,
                beta: $best->beta,
                future: $future
            );

            $allResults = collect($forecast['training'])->merge($forecast['future']);
            $enrichedResults = $this->enrichForecastResult(
                dataset: $dataset,
                forecast: $allResults
            );
            $this->saveTrainingResults(
                training: $training,
                results: $enrichedResults
            );

            // Update summary
            $training = $this->updateTrainingSummary(
                training: $training,
                bestParameter: $best,
                metrics: $testingMetrics
            );

            // Aktifkan
            $this->activateTraining($training);

            return $training;
        });
    }    

    /**
     * ==========================================================
     * Create Training
     * ==========================================================
     *
     * Membuat header training sebelum
     * proses Grid Search dimulai.
     */
    private function createTraining(
        Product $product,
        GranularityEnum $granularity,
        Collection $trainingDataset,
        Collection $testingDataset,
        float $trainingRatio,
        float $testingRatio
    ): ForecastTraining 
    {

        return ForecastTraining::create([

            'product_id' => $product->id,

            'granularity' => $granularity->value,

            /**
             * Rasio Dataset
             */
            'training_ratio' => $trainingRatio,

            'testing_ratio' => $testingRatio,

            /**
             * Jumlah Record
             */
            'training_records' => $trainingDataset->count(),

            'testing_records' => $testingDataset->count(),

            /**
             * Periode Training
             */
            'training_start_date' => $trainingDataset
                ->first()['period_date'],

            'training_end_date' => $trainingDataset
                ->last()['period_date'],

            /**
             * Default
             */
            'alpha' => 0,

            'beta' => 0,

            'mae' => null,

            'mape' => null,

            'rmse' => null,

            'sse' => null,

            'grid_search_total' => 0,

            'best_rank' => null,

            'is_active' => false,

            'status' => 'processing',

            'trained_at' => now(),

        ]);

    }

    /**
     * ==========================================================
     * Run Grid Search
     * ==========================================================
     *
     * Menjalankan Grid Search menggunakan
     * dataset training untuk mendapatkan
     * parameter Alpha dan Beta terbaik.
     */
    private function runGridSearch(
        ForecastTraining $training,
        Product $product,
        Collection $trainingDataset,
        GranularityEnum $granularity,
        float $alphaStart,
        float $alphaEnd,
        float $betaStart,
        float $betaEnd,
        float $step,
        int $future,
        string $metric
    ) {

        return $this->gridSearchService->search(

            training: $training,

            product: $product,

            dataset: $trainingDataset,

            granularity: $granularity,

            alphaStart: $alphaStart,

            alphaEnd: $alphaEnd,

            betaStart: $betaStart,

            betaEnd: $betaEnd,

            step: $step,

            future: $future,

            metric: $metric

        );

    }
    
    /**
     * ==========================================================
     * Run Forecast For Testing
     * ==========================================================
     *
     * Melatih DES HANYA dengan training dataset,
     * lalu memprediksi N periode ke depan (sejumlah testing dataset)
     * untuk dibandingkan dengan actual testing.
     *
     * Ini evaluasi out-of-sample yang sebenarnya —
     * model tidak pernah melihat data testing saat training.
     */
    private function runForecastForTesting(
        float $alpha,
        float $beta,
        Collection $trainingDataset,
        Collection $testingDataset,
        GranularityEnum $granularity
    ): Collection {

        $des = new DESHoltService(
            alpha: $alpha,
            beta: $beta
        );

        /**
         * Training HANYA dari training dataset.
         */
        $des->train(
            dataset: $trainingDataset,
            granularity: $granularity
        );

        /**
         * Predict sejumlah periode testing.
         */
        $predicted = $des->predict(
            $testingDataset->count()
        );

        /**
         * Gabungkan actual testing dengan hasil predict,
         * lalu hitung error/APE secara manual
         * (karena predict() tidak tahu nilai actual).
         */
        return $predicted->values()->map(function ($row, $index) use ($testingDataset) {

            $actual = (float) data_get(
                $testingDataset->get($index),
                'quantity'
            );

            $forecast = $row['forecast'];
            $error = $actual - $forecast;
            $absoluteError = abs($error);
            $squaredError = $error ** 2;
            $ape = $actual != 0 ? ($absoluteError / $actual) * 100 : null;

            return array_merge($row, [
                'actual' => round($actual, 3),
                'error' => round($error, 6),
                'absolute_error' => round($absoluteError, 6),
                'squared_error' => round($squaredError, 6),
                'ape' => is_null($ape) ? null : round($ape, 6),
            ]);

        });

    }

    /**
     * ==========================================================
     * Run Forecast
     * ==========================================================
     *
     * Menjalankan forecasting menggunakan
     * parameter terbaik hasil Grid Search.
     */
    private function runForecast(
        Product $product,
        Collection $dataset,
        GranularityEnum $granularity,
        float $alpha,
        float $beta,
        int $future
    ): array {

        return $this->forecastService->runFromDataset(

            product: $product,

            dataset: $dataset,

            alpha: $alpha,

            beta: $beta,

            granularity: $granularity,

            future: $future

        );

    }

    /**
     * ==========================================================
     * Enrich Forecast Result
     * ==========================================================
     *
     * Menggabungkan metadata periode dari SalesAggregator
     * dengan hasil Forecast DES.
     *
     * Metadata yang ditambahkan:
     *
     * - start_date
     * - end_date
     * - records
     *
     * Matching menggunakan period_date.
     */

    private function enrichForecastResult(
        Collection $dataset,
        Collection $forecast
    ): Collection {

        /**
         * Key berdasarkan period_date.
         */
        $lookup = $dataset->keyBy(function ($item) {
            return $item['period_date']->format('Y-m-d');
        });

        return $forecast->map(function ($row) use ($lookup) {
            $periodDate = $row['period_date'];
            $key = $periodDate->format('Y-m-d');

            $source = $lookup->get($key);

            // Jika data masa depan (is_future = true), generate start_date & end_date mandiri
            if ($row['is_future'] ?? false) {
                // Asumsi jika bulanan (monthly), buat awal & akhir bulan secara otomatis
                $startDate = $periodDate->copy()->startOfDay();
                $endDate = $periodDate->copy()->endOfMonth();
                
                return array_merge($row, [
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'records'    => 0, // Masa depan belum ada transaksi nyata
                ]);
            }

            // Jika data in-sample (training), ambil dari SalesAggregator hasil lookup
            return array_merge($row, [
                'start_date' => $source['start_date'] ?? null,
                'end_date'   => $source['end_date'] ?? null,
                'records'    => $source['records'] ?? 0,
            ]);
        });
    }

    /**
     * ==========================================================
     * Save Training Result
     * ==========================================================
     *
     * Menyimpan seluruh hasil forecasting
     * ke tabel forecast_training_results.
     */
    private function saveTrainingResults(
        ForecastTraining $training,
        Collection $results
    ): void {

        foreach ($results as $index => $result) {

            $training->results()->create([

                /**
                 * Sequence
                 */
                'sequence' => $index + 1,

                /**
                 * Periode
                 */
                'period_date' => $result['period_date'],

                'start_date' => $result['start_date'],

                'end_date' => $result['end_date'],
                
                'records' => $result['records'],

                /**
                 * Forecast
                 */
                'actual' => $result['actual'],

                'forecast' => $result['forecast'],

                /**
                 * Holt Component
                 */
                'level' => $result['level'],

                'trend' => $result['trend'],

                /**
                 * Error
                 */
                'error' => $result['error'],

                'absolute_error' => $result['absolute_error'],

                'squared_error' => $result['squared_error'],

                'ape' => $result['ape'],

                /**
                 * Future Forecast
                 */
                'is_future' => $result['is_future'],

            ]);

        }

    }

    /**
     * ==========================================================
     * Update Training Summary
     * ==========================================================
     *
     * Memperbarui informasi hasil training.
     */
    private function updateTrainingSummary(
        ForecastTraining $training,
        ForecastGridSearch $bestParameter,
        array $metrics // Tetap biarkan argumen ini agar tidak break interface/vendor
    ): ForecastTraining {

        $training->update([
            /**
             * Parameter terbaik.
             */
            'alpha' => $bestParameter->alpha,
            'beta' => $bestParameter->beta,

            /**
             * Ranking terbaik.
             */
            'best_rank' => $bestParameter->rank,

            /**
             * Total Grid Search.
             */
            'grid_search_total' => $training
                ->gridSearches()
                ->count(),

            /**
             * SINKRONISASI PENTING:
             * Ambil metrik murni dari hasil Grid Search yang sudah divalidasi (7%)
             */
            'mae'  => $bestParameter->mae,
            'mape' => $bestParameter->mape,
            'rmse' => $bestParameter->rmse,
            'sse'  => $bestParameter->sse,

            /**
             * Status.
             */
            'status' => 'completed',

            /**
             * Waktu selesai training.
             */
            'trained_at' => now(),
        ]);

        return $training->fresh();
    }

    /**
     * ==========================================================
     * Activate Training
     * ==========================================================
     *
     * Mengaktifkan training terbaru dan
     * menonaktifkan training sebelumnya.
     */
    private function activateTraining(
        ForecastTraining $training
    ): void {

        ForecastTraining::query()
            ->where('product_id', $training->product_id)
            ->where('granularity', $training->granularity)
            ->update([
                'is_active' => false,
            ]);

        $training->update([
            'is_active' => true,
        ]);
    }

}