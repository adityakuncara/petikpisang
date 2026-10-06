<?php

declare(strict_types=1);

namespace App\Models\Forecast;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use App\Models\Forecast\ForecastTrainingLog;
use Spatie\Activitylog\Support\LogOptions;

class ForecastGridSearch extends Model
{
    use LogsActivity;
    use SoftDeletes;

    /**
     * Nama tabel.
     */
    protected $table = 'forecast_grid_searchs';

    /**
     * Mass Assignment.
     */
    protected $fillable = [
        'forecast_training_id',
        'rank',
        'iteration',
        'alpha',
        'beta',
        'metric',
        'mae',
        'mape',
        'rmse',
        'sse',
        'is_best',
    ];

    /**
     * Casting.
     */
    protected function casts(): array
    {
        return [
            'alpha' => 'float',
            'beta' => 'float',
            'mae' => 'float',
            'mape' => 'float',
            'rmse' => 'float',
            'sse' => 'float',
            'rank' => 'integer',
            'is_best' => 'boolean',
        ];
    }

    /**
     * ============================================================
     * Relationship
     * ============================================================
     */

    /**
     * Header Training.
     */
    public function training(): BelongsTo
    {
        return $this->belongsTo(
            ForecastTraining::class,
            'forecast_training_id'
        );
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(
            ForecastTrainingLog::class,
            'forecast_training_id'
        );
    }
}