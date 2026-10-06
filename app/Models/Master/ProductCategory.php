<?php

declare(strict_types=1);

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Master\Product;

class ProductCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [

        'name',

        'description',

        'is_active',

    ];

    protected $casts = [

        'is_active' => 'boolean',

    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}