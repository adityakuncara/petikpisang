<?php

declare(strict_types=1);

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Transaksi\Sale;
use App\Models\Forecast\ForecastTraining;
use App\Enums\UnitEnum;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [

        'product_category_id',
        'code',
        'name',
        'unit',
        'selling_price',
        'description',
        'stock',
        'is_active',

    ];

    protected $casts = [

        'selling_price' => 'decimal:3',
        'is_active' => 'boolean',
        'unit' => UnitEnum::class,

    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class,'product_category_id');
    }
    
    public function sales(): HasMany
    {
        return $this->hasMany(
            Sale::class,
            'product_id'
        );
    }

    public function forecastTrainings()
    {
        return $this->hasMany(ForecastTraining::class);
    }
}