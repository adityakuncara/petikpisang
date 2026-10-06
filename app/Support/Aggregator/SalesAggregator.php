<?php

declare(strict_types=1);

namespace App\Support\Aggregator;

use App\Enums\GranularityEnum;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * ==========================================================================
 * SalesAggregator
 * ==========================================================================
 *
 * Bertugas mengubah data penjualan harian menjadi data time-series
 * berdasarkan granularitas tertentu (Weekly / Monthly).
 *
 * Tanggung Jawab:
 * ----------------
 * ✓ Validasi dataset
 * ✓ Mengurutkan data berdasarkan tanggal
 * ✓ Melakukan agregasi (Weekly / Monthly)
 * ✓ Mengisi periode yang hilang (Missing Period)
 * ✓ Menghasilkan format data yang konsisten
 *
 * Tidak Bertugas:
 * ----------------
 * ✗ Forecast DES
 * ✗ Grid Search
 * ✗ Perhitungan Error (MAE, MAPE, RMSE, SSE)
 * ✗ Training / Testing
 *
 * Output akan digunakan langsung oleh DES Holt Service.
 */
class SalesAggregator
{
    /**
     * ----------------------------------------------------------------------
     * Aggregate Sales
     * ----------------------------------------------------------------------
     *
     * Flow:
     *
     * Validate
     * ↓
     * Sort
     * ↓
     * Aggregate
     * ↓
     * Fill Missing Period
     * ↓
     * Return Collection
     */
    public function aggregate(iterable $sales, GranularityEnum $granularity): Collection
    {
        $sales = collect($sales);

        if ($sales->isEmpty()) {
            return collect();
        }

        $this->validate($sales);
        $sales = $this->sort($sales);

        $result = match ($granularity) {
            GranularityEnum::WEEKLY  => $this->aggregateWeekly($sales),
            GranularityEnum::MONTHLY => $this->aggregateMonthly($sales),
        };
        return $this->fillMissingPeriods($result, $granularity);
    }

    /**
     * Ekstrak tanggal secara aman.
     * Mendukung string, Carbon, maupun Eloquent model.
     */
    private function parseDate(mixed $sale, string $field): Carbon
    {
        $value = data_get($sale, $field);

        if ($value instanceof Carbon) {
            return $value->copy(); // ← langsung copy, jangan parse ulang
        }

        return Carbon::parse($value);
    }

    /**
     * ----------------------------------------------------------------------
     * Validate Dataset
     * ----------------------------------------------------------------------
     *
     * Memastikan setiap data memiliki:
     *
     * - sale_date
     * - quantity
     */
    private function validate(Collection $sales): void
    {
        foreach ($sales as $sale) {

            if (!isset($sale['sale_date']) && !isset($sale->sale_date)) {

                throw new InvalidArgumentException(
                    'Field sale_date is required.'
                );

            }

            if (!isset($sale['quantity']) && !isset($sale->quantity)) {

                throw new InvalidArgumentException(
                    'Field quantity is required.'
                );

            }
        }
    }

    /**
     * ----------------------------------------------------------------------
     * Sort Dataset
     * ----------------------------------------------------------------------
     *
     * Mengurutkan data berdasarkan tanggal penjualan.
     */
    private function sort(Collection $sales): Collection
    {
        return $sales
            ->sortBy(fn($item) => $this->parseDate($item, 'sale_date'))
            ->values();
    }

    /**
     * ----------------------------------------------------------------------
     * Aggregate Weekly (ISO-8601)
     * ----------------------------------------------------------------------
     *
     * Mengelompokkan data berdasarkan minggu ISO.
     *
     * Contoh:
     *
     * 2026-W01
     * 2026-W02
     * 2026-W03
     */
    private function aggregateWeekly(Collection $sales): Collection
    {
        return $sales->groupBy(function ($sale) {
                    $date = $this->parseDate($sale, 'sale_date');
                    return sprintf('%d-W%02d', $date->isoWeekYear(), $date->isoWeek());
                })->map(function (Collection $items, string $period) {

                /**
                 * Ambil tanggal awal minggu
                 */
                [$year, $week] = explode('-W', $period);
                $startDate = Carbon::now()
                    ->setISODate((int)$year, (int)$week)
                    ->startOfWeek(Carbon::MONDAY);
                $endDate = $startDate
                    ->copy()
                    ->endOfWeek(Carbon::SUNDAY);

                return $this->createResult(
                    period: $period, 
                    startDate: $startDate, 
                    endDate: $endDate, 
                    quantity: (float) $items->sum(fn($item) => (float) data_get($item, 'quantity')),
                    records: $items->count(),);
            })->values();
    }

    /**
     * ----------------------------------------------------------------------
     * Aggregate Monthly
     * ----------------------------------------------------------------------
     *
     * Mengelompokkan data berdasarkan bulan.
     *
     * Contoh:
     *
     * 2026-01
     * 2026-02
     * 2026-03
     */
    private function aggregateMonthly(Collection $sales): Collection
    {
        return $sales
            ->groupBy(function ($sale) {
                return $this->parseDate($sale, 'sale_date')->format('Y-m');
            })
            ->map(function (Collection $items, string $period) {

                // Gunakan Carbon::parse dengan tanggal lengkap, bukan createFromFormat
                $startDate = Carbon::parse($period . '-01')->startOfDay();
                $endDate   = Carbon::parse($period . '-01')->endOfMonth();

                return $this->createResult(
                    period:    $period,
                    startDate: $startDate,
                    endDate:   $endDate,
                    quantity:  (float) $items->sum(fn($item) => (float) data_get($item, 'quantity')),
                    records:   $items->count(),
                );
            })
            ->values();
    }

    /**
     * ----------------------------------------------------------------------
     * Period Key
     * ----------------------------------------------------------------------
     *
     * Membuat key unik berdasarkan granularitas.
     *
     * Monthly :
     * 2026-01
     *
     * Weekly :
     * 2026-W01
     */
    private function periodKey(Carbon $date, GranularityEnum $granularity): string {

        return match ($granularity) {

            GranularityEnum::MONTHLY =>

                $date->format('Y-m'),

            GranularityEnum::WEEKLY =>

                sprintf(
                    '%d-W%02d',
                    $date->isoWeekYear(),
                    $date->isoWeek()
                ),

        };

    }

    /**
     * ----------------------------------------------------------------------
     * Next Period
     * ----------------------------------------------------------------------
     *
     * Berpindah ke periode berikutnya.
     */
    private function nextPeriod(Carbon $date, GranularityEnum $granularity): Carbon {

        return match ($granularity) {

            GranularityEnum::MONTHLY =>

                $date
                    ->copy()
                    ->addMonth()
                    ->startOfMonth(),

            GranularityEnum::WEEKLY =>

                $date
                    ->copy()
                    ->addWeek()
                    ->startOfWeek(Carbon::MONDAY),

        };

    }    

    /**
     * ----------------------------------------------------------------------
     * Empty Period
     * ----------------------------------------------------------------------
     *
     * Membuat periode kosong jika tidak ada transaksi.
     */
    private function emptyPeriod(Carbon $date, GranularityEnum $granularity): array {

        return $this->createResult(

            period: $this->periodKey(
                $date,
                $granularity
            ),

            startDate: match ($granularity) {

                GranularityEnum::MONTHLY =>

                    $date
                        ->copy()
                        ->startOfMonth(),

                GranularityEnum::WEEKLY =>

                    $date
                        ->copy()
                        ->startOfWeek(Carbon::MONDAY),

            },

            endDate: match ($granularity) {

                GranularityEnum::MONTHLY =>

                    $date
                        ->copy()
                        ->endOfMonth(),

                GranularityEnum::WEEKLY =>

                    $date
                        ->copy()
                        ->endOfWeek(Carbon::SUNDAY),

            },

            quantity: 0,

            records: 0

        );

    }

    /**
     * ----------------------------------------------------------------------
     * Fill Missing Period
     * ----------------------------------------------------------------------
     *
     * DES Holt membutuhkan data time-series yang kontinu.
     *
     * Method ini akan mengisi periode yang hilang dengan
     * quantity = 0.
     *
     * Contoh:
     *
     * Jan
     * Mar
     *
     * menjadi
     *
     * Jan
     * Feb (0)
     * Mar
     */
private function fillMissingPeriods(Collection $periods, GranularityEnum $granularity): Collection
{
    if ($periods->isEmpty()) {
        return collect();
    }

    $periods = $periods
        ->sortBy(fn($item) => $item['period_date']->timestamp)
        ->values();

    $lookup = $periods->keyBy(function ($item) use ($granularity) {
        return $this->periodKey($item['period_date'], $granularity);
    });

    $current = $periods->first()['period_date']->copy();
    $last    = $periods->last()['period_date']->copy();

    $filled = collect();

    while ($current->lte($last)) {
        $key = $this->periodKey($current, $granularity);

        if ($lookup->has($key)) {
            $filled->push($lookup->get($key));
        } else {
            $filled->push($this->emptyPeriod($current, $granularity));
        }

        $current = $this->nextPeriod($current, $granularity);
    }

    return $filled->values();
}

    /**
     * ----------------------------------------------------------------------
     * Create Result
     * ----------------------------------------------------------------------
     *
     * Menyamakan struktur output agar seluruh Service Forecast
     * menggunakan format data yang konsisten.
     */
    private function createResult(string $period, Carbon $startDate, Carbon $endDate, float $quantity, int $records): array {

        return [

            /**
             * Label periode
             * untuk tampilan.
             */
            'period' => $period,

            /**
             * Acuan seluruh engine forecast.
             */
            'period_date' => $startDate->copy(),

            /**
             * Tetap disimpan jika nanti
             * dibutuhkan laporan.
             */
            'start_date' => $startDate->copy(),

            'end_date' => $endDate->copy(),

            'quantity' => round(
                $quantity,
                3
            ),

            'records' => $records,

        ];
    }
}

