<?php

declare(strict_types=1);

namespace App\Support\Metrics;

use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * ==========================================================================
 * Forecast Metric
 * ==========================================================================
 *
 * Bertugas menghitung nilai evaluasi model forecasting.
 *
 * Metric yang didukung:
 *
 * - MAE
 * - MAPE
 * - RMSE
 * - SSE
 *
 * Class ini TIDAK menghitung forecast.
 *
 * Forecast sudah dihasilkan oleh DESHoltService.
 */
class ForecastMetric
{
    /**
     * ----------------------------------------------------------------------
     * Calculate Metrics
     * ----------------------------------------------------------------------
     *
     * Menghitung seluruh metric evaluasi.
     */
    public function calculate(
        Collection $result
    ): array {
        $this->validate($result);
        /**
         * Abaikan record pertama
         * (forecast masih NULL).
         */
        $result = $result
            ->whereNotNull('forecast')
            ->values();
        return [
            'mae' => $this->mae($result),
            'mape' => $this->mape($result),
            'wmape' => $this->wmape($result),
            'rmse' => $this->rmse($result),
            'sse' => $this->sse($result),
        ];

    }

    /**
     * ----------------------------------------------------------------------
     * Mean Absolute Error
     * ----------------------------------------------------------------------
     */
    public function mae(
        Collection $result
    ): float {

        return round(
            $result->avg('absolute_error'), 6
        );

    }

    /**
     * ----------------------------------------------------------------------
     * Mean Absolute Percentage Error
     * ----------------------------------------------------------------------
     */
    public function mape(
        Collection $result
    ): float {
        return round(
            $result
                ->whereNotNull('ape')
                ->avg('ape'), 6
                );
    }

    /**
     * ----------------------------------------------------------------------
     * Weighted Mean Absolute Percentage Error
     * ----------------------------------------------------------------------
     *
     * Lebih stabil dibanding MAPE biasa ketika dataset
     * memiliki banyak periode dengan actual kecil atau nol
     * (mis. produk stockout / tidak laku di periode tertentu).
     *
     * Rumus:
     *
     * WMAPE = (Σ |actual - forecast|) / (Σ actual) × 100
     */
    public function wmape(
        Collection $result
    ): float {

        $totalActual = $result->sum('actual');

        if ($totalActual == 0) {
            return 0.0;
        }

        return round(
            ($result->sum('absolute_error') / $totalActual) * 100, 6
        );

    }
    
    /**
     * ----------------------------------------------------------------------
     * Root Mean Squared Error
     * ----------------------------------------------------------------------
     */
    public function rmse(
        Collection $result
    ): float {
        return round(
            sqrt(
                $result
                    ->avg('squared_error')),6
        );
    }

    /**
     * ----------------------------------------------------------------------
     * Sum Squared Error
     * ----------------------------------------------------------------------
     */
    public function sse(
        Collection $result
    ): float {
        return round(
            $result
                ->sum('squared_error'),6
        );
    }

    /**
     * ----------------------------------------------------------------------
     * Validate Dataset
     * ----------------------------------------------------------------------
     */
    private function validate(
        Collection $result
    ): void {

        if ($result->isEmpty()) {
            throw new InvalidArgumentException(
                'Forecast result is empty.'
            );

        }

        foreach ($result as $row) {

            foreach (
                [
                    'forecast',
                    'absolute_error',
                    'squared_error',
                    'ape',
                ]
                as $field
            ) {

                if (! array_key_exists($field, $row)) {
                    throw new InvalidArgumentException(
                        "Field {$field} is required."
                    );
                }
            }
        }
    }
}