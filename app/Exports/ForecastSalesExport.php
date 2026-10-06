<?php

declare(strict_types=1);

namespace App\Exports;;

use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ForecastSalesExport
{
    public function __construct(
        protected array $summary,
        protected array $training,
        protected array $future,
        protected string $productName,
        protected string $granularity,
    ) {}

    public function download():  StreamedResponse
    {
        $rows = [];

        /*
        |--------------------------------------------------------------------------
        | INFORMASI
        |--------------------------------------------------------------------------
        */

        $rows[] = ['FORECAST PENJUALAN'];
        $rows[] = [];

        $rows[] = ['Produk', $this->productName];
        $rows[] = ['Granularity', ucfirst($this->granularity)];
        $rows[] = [];

        /*
        |--------------------------------------------------------------------------
        | RINGKASAN MODEL
        |--------------------------------------------------------------------------
        */

        $rows[] = ['RINGKASAN MODEL'];

        $rows[] = [
            'Alpha',
            'Beta',
            'MAE',
            'MAPE',
            'RMSE',
            'SSE',
        ];

        $rows[] = [

            $this->summary['alpha'],

            $this->summary['beta'],

            $this->summary['mae'],

            $this->summary['mape'],

            $this->summary['rmse'],

            $this->summary['sse'],

        ];

        $rows[] = [];

        /*
        |--------------------------------------------------------------------------
        | FUTURE FORECAST
        |--------------------------------------------------------------------------
        */

        $rows[] = ['FUTURE FORECAST'];

        $rows[] = [
            'No',
            'Periode',
            'Prediksi',
            'Level',
            'Trend',
            'Interval Prediksi',
            'Status',
        ];

        foreach ($this->future as $i => $row) {

            $rows[] = [

                $i + 1,

                $row['period_date']->translatedFormat('F Y'),

                number_format((float) $row['forecast'], 0),

                $row['level'],

                $row['trend'],

                $row['interval_lower'] . ' - ' . $row['interval_upper'],

                $row['level'] = 50 ? 'Optimal' : 'Prediksi Naik/Turun',

            ];

        }

        $rows[] = [];

        /*
        |--------------------------------------------------------------------------
        | TRAINING
        |--------------------------------------------------------------------------
        */

        $rows[] = ['HASIL PERHITUNGAN'];

        $rows[] = [

            'No',

            'Periode',

            'Actual',

            'Forecast',

            'Level',

            'Trend',

            'Error',

            'Absolute Error',

            'Squared Error',

            'APE',

        ];

        foreach ($this->training as $row) {

            $rows[] = [

                $row['index'],

                $row['period_date']->format('Y-m'),

                $row['actual'],

                $row['forecast'],

                $row['level'],

                $row['trend'],

                $row['error'],

                $row['absolute_error'],

                $row['squared_error'],

                $row['ape'],

            ];

        }

        $filename = sprintf(

            'forecast-%s-%s.xlsx',

            now()->format('YmdHis'),

            str($this->productName)->slug()

        );

        return (new FastExcel(collect($rows)))
            ->download($filename);
    }
}