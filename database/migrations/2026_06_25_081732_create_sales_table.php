<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {

            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('sale_date');

            $table->decimal('quantity', 12, 3);

            $table->decimal('unit_price', 15, 2);

            $table->decimal('total_revenue', 15, 2);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->timestamps();

            $table->unique([
                'product_id',
                'sale_date',
            ]);

            $table->index('sale_date');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};