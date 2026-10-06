<?php

declare(strict_types=1);

namespace App\Services\Forecast;

use App\Enums\GranularityEnum;
use App\Models\Forecast\ForecastGridSearch;
use App\Models\Forecast\ForecastTraining;
use App\Models\Forecast\ForecastTrainingResult;
use App\Models\Master\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GridSearchService
{
    /**
     * Forecast Service.
     */
    protected ForecastService $forecastService;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->forecastService = new ForecastService();
    }

    /**
     * ==============================================================
     * Grid Search
     * ==============================================================
     *
     * Melakukan pencarian parameter Alpha dan Beta terbaik
     * menggunakan metode Grid Search.
     */
    public function search(
        ForecastTraining $training,
        Product $product,
        Collection $dataset,
        GranularityEnum $granularity,
        float $alphaStart = 0.10,
        float $alphaEnd = 0.90,
        float $betaStart = 0.10,
        float $betaEnd = 0.90,
        float $step = 0.01,
        int $future = 3,
        string $metric = 'wmape'
    ): ForecastGridSearch {

        /**
         * Validasi Dataset.
         */
        if ($dataset->count() < 2) {
            throw new InvalidArgumentException(
                'Dataset minimal terdiri dari dua periode.'
            );
        }

        /**
         * Generate kombinasi Alpha & Beta.
         */
        $parameters = $this->buildParameters(
            alphaStart: $alphaStart,
            alphaEnd: $alphaEnd,
            betaStart: $betaStart,
            betaEnd: $betaEnd,
            step: $step
        );

        /**
         * Loop Grid Search.
         *
         * Akan kita isi pada bagian berikutnya.
         */

        DB::transaction(function () use (
            $training,
            $product,
            $dataset,
            $granularity,
            $future,
            $parameters,
            $metric
        ) {

            foreach ($parameters as $parameter) {
                /**
                 * Jalankan Forecast
                 */
                $forecast = $this->forecastService->runFromDataset(
                    product: $product,
                    dataset: $dataset,
                    alpha: $parameter['alpha'],
                    beta: $parameter['beta'],
                    granularity: $granularity,
                    future: $future
                );

                /**
                 * Simpan hasil Grid Search
                 */
                $this->saveResult(
                    training: $training,
                    parameter: $parameter,
                    metrics: $forecast['metrics'],
                    metric: $metric
                );
            }

        });

        /**
         * Ranking.
         */
        $this->ranking(
            $training,
            $metric
        );

        /**
         * Return parameter terbaik.
         */
        return $this->best(
            $training
        );

    }
    /**
     * ==============================================================
     * Generate Range
     * ==============================================================
     *
     * Menghasilkan rentang angka.
     *
     * Contoh:
     *
     * start = 0.10
     * end   = 0.50
     * step  = 0.10
     *
     * hasil:
     *
     * 0.10
     * 0.20
     * 0.30
     * 0.40
     * 0.50
     */
    private function generateRange(float $start, float $end, float $step): array {

        if ($step <= 0) {
            throw new InvalidArgumentException(
                'Step harus lebih besar dari nol.'
            );
        }

        $values = [];
        for ($value = $start; $value <= $end + 0.000001; $value += $step) {
            $values[] = round($value, 2);
        }
        return $values;
    }

    /**
     * ==============================================================
     * Build Parameters
     * ==============================================================
     *
     * Menghasilkan seluruh kombinasi Alpha dan Beta.
     *
     * Contoh:
     *
     * Alpha : 0.1 - 0.3
     * Beta  : 0.1 - 0.3
     *
     * Hasil :
     *
     * α=0.1 β=0.1
     * α=0.1 β=0.2
     * α=0.1 β=0.3
     * α=0.2 β=0.1
     * ...
     */
    public function buildParameters(
        float $alphaStart,
        float $alphaEnd,
        float $betaStart,
        float $betaEnd,
        float $step
    ): Collection 
    {

        $alphas = $this->generateRange(
            $alphaStart,
            $alphaEnd,
            $step
        );

        $betas = $this->generateRange(
            $betaStart,
            $betaEnd,
            $step
        );

        $parameters = collect();
        foreach ($alphas as $alpha) {
            foreach ($betas as $beta) {
                $parameters->push([
                    'iteration' => $parameters->count() + 1,
                    'alpha' => $alpha,
                    'beta'  => $beta,
                    'rank'=>null,
                    'is_best'=>false,
                ]);

            }

        }

        return $parameters;

    }

    /**
     * ==============================================================
     * Save Grid Search Result
     * ==============================================================
     *
     * Menyimpan satu hasil Grid Search.
     *
     * Setiap kombinasi Alpha-Beta akan menghasilkan
     * satu record pada tabel forecast_grid_searches.
     */
    private function saveResult(
        ForecastTraining $training,
        array $parameter,
        array $metrics,
        string $metric
    ): ForecastGridSearch 
    {

        return ForecastGridSearch::create([

            /**
             * Header Training
             */
            'forecast_training_id' => $training->id,

            /**
             * Iterasi Grid Search
             */
            'iteration' => $parameter['iteration'],

            /**
             * Ranking
             *
             * Diisi setelah proses ranking selesai.
             */
            'rank' => null,

            /**
             * Parameter Holt
             */
            'alpha' => $parameter['alpha'],
            'beta' => $parameter['beta'],

            /**
             * Metric
             */
            'metric' => $metric,
            'mae' => $metrics['mae'],
            'mape' => $metrics['mape'],
            'rmse' => $metrics['rmse'],
            'sse' => $metrics['sse'],

            /**
             * Belum diketahui apakah terbaik.
             */
            'is_best' => false,

        ]);

    }

    /**
     * ==============================================================
     * Ranking
     * ==============================================================
     *
     * Memberikan ranking berdasarkan metric yang dipilih.
     *
     * Metric yang didukung:
     * - mae
     * - mape
     * - rmse
     * - sse
     */
    private function ranking(
        ForecastTraining $training,
        string $metric = 'mae'
    ): void 
    {

        $allowedMetrics = [
            'mae',
            'mape',
            'wmape',
            'rmse',
            'sse',
        ];

        if (! in_array($metric, $allowedMetrics)) {
            throw new InvalidArgumentException(
                "Metric '{$metric}' tidak didukung."
            );
        }

        /**
         * Reset seluruh ranking.
         */
        ForecastGridSearch::query()
            ->where(
                'forecast_training_id',
                $training->id
            )
            ->update([
                'rank' => null,
                'is_best' => false,
            ]);

        /**
         * Urutkan berdasarkan metric terkecil.
         */
        $results = ForecastGridSearch::query()
            ->where(
                'forecast_training_id',
                $training->id
            )
            ->orderBy($metric)
            ->orderBy('iteration')
            ->get();

        /**
         * Berikan ranking.
         */
        foreach ($results as $index => $result) {
            $result->update([
                'rank' => $index + 1,
                'is_best' => ($index === 0),
            ]);
        }
    }

    /**
     * ==============================================================
     * Best Parameter
     * ==============================================================
     *
     * Mengambil kombinasi Alpha dan Beta terbaik
     * berdasarkan hasil ranking.
     */
    private function best(
        ForecastTraining $training
    ): ForecastGridSearch 
    {
        $best = ForecastGridSearch::query()
            ->where(
                'forecast_training_id',
                $training->id
            )
            ->where('is_best', true)
            ->first();
        if (! $best) {
            throw new InvalidArgumentException(
                'Parameter terbaik tidak ditemukan.'
            );
        }
        return $best;
    }
}