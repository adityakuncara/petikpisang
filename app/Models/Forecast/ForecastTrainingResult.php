<?php

declare(strict_types=1);

namespace App\Models\Forecast;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Forecast\ForecastTrainingLog;

class ForecastTrainingResult extends Model
{
    protected $fillable = [
        'forecast_training_id',
        'sequence',
        'period_date',
        'start_date',
        'end_date',
        'actual',
        'forecast',
        'level',
        'trend',
        'error',
        'absolute_error',
        'squared_error',
        'ape',
        'is_future',

    ];

    protected $casts = [
        'period_date' => 'date',
        'is_future' => 'boolean',

    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(
            ForecastTraining::class,
            'forecast_training_id'
        );
    }

    public function logs(): HasMany
    {
        return $this->hasMany(
            ForecastTrainingLog::class,
            'forecast_training_id'
        );
    }
}