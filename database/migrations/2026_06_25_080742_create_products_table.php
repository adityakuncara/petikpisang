<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Master\Product;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_category_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('code',30)
                ->unique();

            $table->string('name',150);

            $table->enum('unit',[
                'kg',
                'gr',
                'pcs',
                'bungkus'
            ]);

            $table->decimal('selling_price',12,3)
                ->default(0);

            $table->text('description')
                ->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->softDeletes();

            $table->index('name');

            $table->index('is_active');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};