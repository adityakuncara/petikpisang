<?php

declare(strict_types=1);

namespace App\Models\Transaksi;

use App\Models\Master\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sale_date',
        'quantity',
        'unit_price',
        'total_revenue',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'quantity' => 'decimal:0',
        'unit_price' => 'decimal:2',
        'total_revenue' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}