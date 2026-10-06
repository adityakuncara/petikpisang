<?php

declare(strict_types=1);

namespace App\Models\Forecast;

use App\Models\Master\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForecastTraining extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'granularity',
        'alpha',
        'beta',
        'training_ratio',
        'testing_ratio',
        'training_records',
        'testing_records',
        'mae',
        'mape',
        'rmse',
        'sse',
        'training_start_date',
        'training_end_date',
        'grid_search_total',
        'best_rank',
        'is_active',
        'status',
        'notes',
        'trained_at',
        'created_by',
    ];

    protected $casts = [
        'training_start_date' => 'date',
        'training_end_date' => 'date',
        'trained_at' => 'datetime',
        'alpha' => 'decimal:2',
        'beta' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(
            ForecastTrainingResult::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * Semua hasil Grid Search.
     */
    public function gridSearches(): HasMany
    {
        return $this->hasMany(
            ForecastGridSearch::class
        );
    }


}