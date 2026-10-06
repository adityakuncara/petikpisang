<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

class Formatter
{
    /**
     * ----------------------------------------------------------------------
     * Number Formatter
     * ----------------------------------------------------------------------
     *
     * Format angka umum.
     *
     * Contoh:
     * 12345.6789
     *
     * menjadi
     *
     * 12,345.68
     */
    public static function number(
        float|int|null $value,
        int $decimal = 2
    ): string {

        return number_format(
            (float) ($value ?? 0),
            $decimal,
            '.',
            ','
        );

    }

    /**
     * ----------------------------------------------------------------------
     * Quantity Formatter
     * ----------------------------------------------------------------------
     *
     * Digunakan untuk menampilkan quantity penjualan.
     *
     * Default menggunakan 3 digit desimal.
     */
    public static function quantity(
        float|int|null $value
    ): string {

        return self::number($value, 3);

    }

    /**
     * ----------------------------------------------------------------------
     * Currency Formatter
     * ----------------------------------------------------------------------
     *
     * Format mata uang Rupiah.
     *
     * Contoh:
     *
     * Rp 15,000.00
     */
    public static function currency(float|int|null $value): string
    {
        return 'Rp ' . number_format($value ?? 0, 0, ',', '.');
    }

    /**
     * ----------------------------------------------------------------------
     * Percentage Formatter
     * ----------------------------------------------------------------------
     *
     * Contoh:
     *
     * 8.4567
     *
     * menjadi
     *
     * 8.46%
     */
    public static function percentage(
        float|int|null $value,
        int $decimal = 2
    ): string {

        return self::number(
            $value,
            $decimal
        ) . '%';

    }

    /**
     * ----------------------------------------------------------------------
     * Forecast Metric Formatter
     * ----------------------------------------------------------------------
     *
     * Digunakan untuk:
     *
     * MAE
     * MAPE
     * RMSE
     * SSE
     */
    public static function metric(
        float|int|null $value
    ): string {

        return self::number(
            $value,
            6
        );

    }

    /**
     * ----------------------------------------------------------------------
     * Date Formatter
     * ----------------------------------------------------------------------
     *
     * Format:
     *
     * 25 Jun 2026
     */
    public static function date(
        Carbon|string|null $date,
        string $format = 'd M Y'
    ): ?string {

        if (blank($date)) {
            return null;
        }

        return Carbon::parse($date)
            ->translatedFormat($format);

    }

    /**
     * ----------------------------------------------------------------------
     * Period Formatter
     * ----------------------------------------------------------------------
     *
     * Weekly:
     * 2026-W01
     *
     * menjadi
     *
     * Week 01 - 2026
     *
     * Monthly:
     * 2026-01
     *
     * menjadi
     *
     * Jan 2026
     */
    public static function period(
        string $period
    ): string {

        if (str_contains($period, '-W')) {

            [$year, $week] = explode('-W', $period);

            return sprintf(
                'Week %02d - %s',
                $week,
                $year
            );

        }

        return Carbon::createFromFormat(
            'Y-m',
            $period
        )->translatedFormat('M Y');

    }

    /**
     * ----------------------------------------------------------------------
     * Nullable Formatter
     * ----------------------------------------------------------------------
     *
     * Mengubah nilai NULL menjadi "-".
     */
    public static function nullable(
        mixed $value,
        string $default = '-'
    ): mixed {

        return blank($value)
            ? $default
            : $value;

    }

    /**
     * ----------------------------------------------------------------------
     * Boolean Formatter
     * ----------------------------------------------------------------------
     *
     * TRUE  -> Ya
     * FALSE -> Tidak
     */
    public static function boolean(
        bool $value
    ): string {

        return $value
            ? 'Ya'
            : 'Tidak';

    }

}

