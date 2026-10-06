<?php

declare(strict_types=1);

namespace App\Support;

class ForecastConfig
{
    public static function trainingRatio(): float
    {
        return (float) config('forecast.training_ratio', 0.8);
    }

    public static function testingRatio(): float
    {
        return (float) config('forecast.testing_ratio', 0.2);
    }

    public static function minimumDataset(): int
    {
        return (int) config('forecast.minimum_dataset', 30);
    }

    public static function alpha(): array
    {
        return config('forecast.alpha');
    }

    public static function beta(): array
    {
        return config('forecast.beta');
    }

    public static function forecast(): array
    {
        return config('forecast.forecast');
    }
}