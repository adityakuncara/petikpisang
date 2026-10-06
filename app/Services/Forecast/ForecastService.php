<?php

declare(strict_types=1);

namespace App\Services\Forecast;

use App\Enums\GranularityEnum;
use App\Models\Master\Product;
use App\Models\Transaksi\Sale;
use App\Support\Aggregator\SalesAggregator;
use App\Support\Metrics\ForecastMetric;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ForecastService
{
    /**
     * Sales Aggregator.
     */
    protected SalesAggregator $aggregator;

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
        $this->metric = new ForecastMetric();
    }

    /**
     * ==============================================================
     * Forecast From Database
     * ==============================================================
     *
     * Mengambil histori penjualan dari database,
     * melakukan agregasi,
     * kemudian menjalankan forecasting.
     */
    public function run(
        Product $product,
        float $alpha,
        float $beta,
        GranularityEnum $granularity,
        int $future = 3
    ): array 
    {

        /**
         * Validasi horizon.
         */
        if ($future < 1) {
            throw new InvalidArgumentException(
                'Future minimal 1 periode.'
            );

        }

        /**
         * Ambil histori penjualan.
         */
        $sales = Sale::query()
            ->where('product_id', $product->id)
            ->orderBy('sale_date')
            ->get();

        if ($sales->isEmpty()) {
            throw new InvalidArgumentException(
                'Data penjualan tidak ditemukan.'
            );

        }

        /**
         * Agregasi data.
         */
        $dataset = $this->aggregator->aggregate(
            sales: $sales,
            granularity: $granularity

        );

        if ($dataset->count() < 2) {
            throw new InvalidArgumentException(
                'Dataset minimal terdiri dari dua periode.'
            );

        }

        /**
         * Seluruh proses forecasting
         * dilakukan pada method ini.
         */
        return $this->runFromDataset(
            product: $product,
            dataset: $dataset,
            alpha: $alpha,
            beta: $beta,
            granularity: $granularity,
            future: $future
        );

    }

    /**
     * ==============================================================
     * Forecast From Dataset
     * ==============================================================
     *
     * Menjalankan proses forecasting menggunakan dataset
     * yang sudah siap (hasil agregasi).
     *
     * Method ini digunakan oleh:
     *
     * - ForecastService::run()
     * - GridSearchService
     * - TrainingService
     */
    public function runFromDataset(
        Product $product,
        Collection $dataset,
        float $alpha,
        float $beta,
        GranularityEnum $granularity,
        int $future = 3
    ): array 
    {

        /**
         * ----------------------------------------------------------
         * Validasi Dataset
         * ----------------------------------------------------------
         */
        if ($dataset->count() < 2) {

            throw new InvalidArgumentException(
                'Dataset minimal terdiri dari dua periode.'
            );

        }

        /**
         * ----------------------------------------------------------
         * DES Holt Engine
         * ----------------------------------------------------------
         */
        $des = new DESHoltService(
            alpha: $alpha,
            beta: $beta
        );

        /**
         * ----------------------------------------------------------
         * Training
         * ----------------------------------------------------------
         */
        $training = $des->train(
            dataset: $dataset,
            granularity: $granularity
        );

        if ($training->isEmpty()) {
            throw new InvalidArgumentException(
                'Training gagal dilakukan.'
            );
        }

        /**
         * ----------------------------------------------------------
         * Hitung Metric
         * ----------------------------------------------------------
         */
        $metrics = $this->metric->calculate(
            $training
        );

        /**
         * ----------------------------------------------------------
         * Future Forecast
         * ----------------------------------------------------------
         */
        $futureForecast = $des->predict(
            $future
        );

        /**
         * ----------------------------------------------------------
         * Return
         * ----------------------------------------------------------
         */
        return [
            'product' => $product,
            'dataset' => $dataset,
            'training' => $training,
            'future' => $futureForecast,
            'metrics' => $metrics,
            'granularity' => $granularity,
            'alpha' => $alpha,
            'beta' => $beta,
        ];

    }
}