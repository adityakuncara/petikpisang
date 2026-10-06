<?php

declare(strict_types=1);

namespace App\Services\Forecast;

use App\Enums\GranularityEnum;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * ==========================================================================
 * Double Exponential Smoothing (Holt)
 * ==========================================================================
 *
 * Pure Forecast Engine
 *
 * --------------------------------------------------------------------------
 * Fungsi
 * --------------------------------------------------------------------------
 *
 * ✓ Menghitung Level
 * ✓ Menghitung Trend
 * ✓ Menghitung Forecast
 * ✓ Menghasilkan prediksi n periode ke depan
 *
 * --------------------------------------------------------------------------
 * Input
 * --------------------------------------------------------------------------
 *
 * Collection
 *
 * [
 *      [
 *          period => 2026-01,
 *          quantity => 120
 *      ]
 * ]
 *
 * --------------------------------------------------------------------------
 * Output
 * --------------------------------------------------------------------------
 *
 * Collection
 *
 * period
 * actual
 * level
 * trend
 * forecast
 *
 */
class DESHoltService
{
    /**
     * Periode terakhir hasil training.
     *
     * Digunakan sebagai acuan
     * saat melakukan forecast ke depan.
     */
    protected ?string $lastPeriod = null;

    /**
     * Alpha Smoothing.
     */
    protected float $alpha;

    /**
     * Beta Trend.
     */
    protected float $beta;

    /**
     * Dataset hasil agregasi.
     */
    protected Collection $dataset;

    /**
     * Hasil perhitungan DES.
     */
    protected Collection $result;

    /**
     * Granularitas dataset.
     */
    protected GranularityEnum $granularity;

    /**
     * Tanggal periode terakhir.
     *
     * Digunakan sebagai acuan predict().
     */
    protected ?Carbon $lastPeriodDate = null;

    /**
     * Level terakhir.
     *
     * Digunakan ketika predict().
     */
    protected float $lastLevel = 0;

    /**
     * Trend terakhir.
     *
     * Digunakan ketika predict().
     */
    protected float $lastTrend = 0;

    /**
     * Constructor.
     */
    public function __construct(
        float $alpha,
        float $beta
    ) {

        $this->alpha = $alpha;
        $this->beta = $beta;
        $this->dataset = collect();
        $this->result = collect();
        $this->validate();
    }

    /**
     * ----------------------------------------------------------------------
     * Train DES Holt
     * ----------------------------------------------------------------------
     *
     * Menghasilkan:
     *
     * Actual
     * Forecast
     * Level
     * Trend
     *
     * untuk seluruh dataset training.
     */
    public function train(Collection $dataset, GranularityEnum $granularity): Collection {
        $this->reset();
        $this->dataset = $dataset->values();
        $this->validateDataset();
        $this->granularity = $granularity;
        $this->initialize();
        
        // Simpan nilai inisialisasi awal ke variabel lokal agar tidak tergeser di loop
        $currentLevel = $this->lastLevel;
        $currentTrend = $this->lastTrend;

        foreach ($this->dataset as $index => $row) {
            $actual = (float) data_get($row, 'quantity');

            /**
             * Rumus Forecast Holt untuk periode t:
             * Ft = Lt-1 + Tt-1
             */
            $forecast = $currentLevel + $currentTrend;

            /**
             * Hitung Level (Lt) & Trend (Tt) baru berdasarkan data aktual saat ini
             */
            $level = ($this->alpha * $actual) + ((1 - $this->alpha) * ($currentLevel + $currentTrend));
            $trend = ($this->beta * ($level - $currentLevel)) + ((1 - $this->beta) * $currentTrend);

            /**
             * Hitung Forecast Error
             */
            $error = $actual - $forecast;
            $absoluteError = abs($error);
            $squaredError = pow($error, 2);
            $ape = $actual != 0 ? ($absoluteError / $actual) * 100 : null;

            /**
             * Simpan hasil training.
             */
            $this->result->push([
                'index' => $index + 1,
                'period_date' => Carbon::parse(data_get($row, 'period_date')),
                'actual' => round($actual, 3),
                // Periode pertama (indeks 0) dialokasikan sebagai baseline inisialisasi
                'forecast' => $index === 0 ? null : round($forecast, 3),
                'error' => $index === 0 ? null : round($error, 6),
                'absolute_error' => $index === 0 ? null : round($absoluteError, 6),
                'squared_error' => $index === 0 ? null : round($squaredError, 6),
                'ape' => ($index === 0 || is_null($ape)) ? null : round($ape, 6),
                'level' => round($level, 6),
                'trend' => round($trend, 6),
                'is_future' => false,
            ]);

            /**
             * Update state untuk iterasi berikutnya.
             */
            $currentLevel = $level;
            $currentTrend = $trend;
            
            // Simpan ke property class untuk keperluan fungsi predict()
            $this->lastLevel = $level;
            $this->lastTrend = $trend;
            $this->lastPeriodDate = Carbon::parse(data_get($row, 'period_date'));
        }
        return $this->result;
    }

    /**
     * Menghasilkan forecast
     * berdasarkan data training.
     */

    /**
     * ----------------------------------------------------------------------
     * Forecast In-Sample
     * ----------------------------------------------------------------------
     *
     * Mengembalikan hasil forecast yang dihasilkan
     * selama proses training.
     *
     * Output:
     *
     * period
     * actual
     * forecast
     */
        public function forecast(): Collection
        {
            if ($this->result->isEmpty()) {
                throw new InvalidArgumentException(
                    'Model belum ditraining.'
                );
            }

            return $this->result->map(function ($row) {
                return [
                    'period' => $row['period_date']->format('Y-m'),
                    'actual' => $row['actual'],
                    'forecast' => $row['forecast'],
                ];

            });

        }

    /**
     * ----------------------------------------------------------------------
     * Future Forecast
     * ----------------------------------------------------------------------
     *
     * Menghasilkan prediksi beberapa periode ke depan
     * berdasarkan hasil training terakhir.
     *
     * Rumus:
     *
     * Ft+m = Lt + (m × Tt)
     *
     * m = horizon
     */
    public function predict(int $horizon = 1): Collection {

        /**
         * Pastikan model sudah ditraining.
         */
        if ($this->result->isEmpty()) {
            throw new InvalidArgumentException(
                'Model belum dilakukan training.'
            );
        }

        if ($horizon < 1) {
            throw new InvalidArgumentException(
                'Horizon minimal 1.'
            );
        }

        $results = collect();
        $currentDate = $this->lastPeriodDate->copy();
        $lastIndex = $this->result->count();

        for ($i = 1; $i <= $horizon; $i++) {
            /**
             * Tentukan periode berikutnya
             * berdasarkan granularitas.
             */
            switch ($this->granularity) {
                case GranularityEnum::WEEKLY:
                    $currentDate->addWeek();
                    break;
                case GranularityEnum::MONTHLY:
                    $currentDate->addMonth();
                    break;
            }

            /**
             * Rumus Forecast Holt
             */
            $forecast = $this->lastLevel + ($i * $this->lastTrend);

            /**
             * Simpan hasil prediksi.
             */
            $results->push([
                'index' => $lastIndex + $i,
                'period_date' => $currentDate->copy(),
                'actual' => null,
                'forecast' => round($forecast,6),
                'error' => null,
                'absolute_error' => null,
                'squared_error' => null,
                'ape' => null,
                'level' => round($this->lastLevel, 4),  // ← Lt konstan untuk semua horizon    
                'trend'=> round($this->lastTrend, 4),  // ← Tt konstan untuk semua horizon
                'interval_lower' => round($forecast - abs($this->lastTrend) * $i, 2), // ← batas bawah
                'interval_upper' => round($forecast + abs($this->lastTrend) * $i, 2), // ← batas atasd
                'is_future' => true,
            ]);

        }
        return $results;

    }

    public function result(): Collection
    {
        return $this->result;
    }

    /**
     * ----------------------------------------------------------------------
     * Validasi Parameter
     * ----------------------------------------------------------------------
     *
     * Alpha
     *
     * 0 < α < 1
     *
     * Beta
     *
     * 0 < β < 1
     */
    private function validate(): void
    {
        if ($this->alpha <= 0 || $this->alpha >= 1) {
            throw new InvalidArgumentException(
                'Alpha harus bernilai > 0 dan < 1.'
            );
        }

        if ($this->beta <= 0 ||$this->beta >= 1) {
            throw new InvalidArgumentException(
                'Beta harus bernilai > 0 dan < 1.'
            );
        }
    }

    /**
     * ----------------------------------------------------------------------
     * Inisialisasi Level dan Trend yang Lebih Stabil
     *
     * Rumus:
     *
     * L0 = Y1
     *
     * T0 = Y2 - Y1
     * ----------------------------------------------------------------------
     */

    private function initialize(): void
    {
        $count = $this->dataset->count();
        if ($count < 2) {
            throw new InvalidArgumentException(
                'Dataset minimal terdiri dari 2 periode.'
            );
        }

        // Jika data cukup banyak (misal >= 4), gunakan rata-rata untuk meredam pencilan (outlier)
        if ($count >= 4) {
            $y1 = (float) data_get($this->dataset[0], 'quantity');
            $y2 = (float) data_get($this->dataset[1], 'quantity');
            $y3 = (float) data_get($this->dataset[2], 'quantity');
            $y4 = (float) data_get($this->dataset[3], 'quantity');

            $this->lastLevel = ($y1 + $y2 + $y3 + $y4) / 4;
            $this->lastTrend = (($y2 - $y1) + ($y3 - $y2) + ($y4 - $y3)) / 3;
        } else {
            // Fallback ke metode standar jika data hanya ada 2 atau 3
            $first = (float) data_get($this->dataset[0], 'quantity');
            $second = (float) data_get($this->dataset[1], 'quantity');

            $this->lastLevel = $first;
            $this->lastTrend = $second - $first;
        }
    }

    /**
     * ----------------------------------------------------------------------
     * Reset Object
     * ----------------------------------------------------------------------
     *
     * Menghapus seluruh hasil perhitungan
     * sebelum dilakukan training ulang.
     */
    private function reset(): void
    {
        $this->lastPeriodDate = null;
        $this->granularity = GranularityEnum::MONTHLY;
        $this->dataset = collect();
        $this->result = collect();
        $this->lastLevel = 0;
        $this->lastTrend = 0;
    }

    /**
     * ----------------------------------------------------------------------
     * Validasi Dataset
     * ----------------------------------------------------------------------
     *
     * Dataset minimal:
     *
     * period_date
     * quantity
     *
     * Minimal terdiri dari 2 data.
     */
    private function validateDataset(): void
    {
        if ($this->dataset->count() < 2) {

            throw new InvalidArgumentException(
                'Dataset minimal terdiri dari 2 periode.'
            );

        }

        foreach ($this->dataset as $row) {

            if (! isset($row['period_date'])) {

                throw new InvalidArgumentException(
                    'Field period_date wajib ada.'
                );

            }

            if (! isset($row['quantity'])) {

                throw new InvalidArgumentException(
                    'Field quantity wajib ada.'
                );

            }

        }
    }
}
