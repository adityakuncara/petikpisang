<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_grid_searches', function (Blueprint $table) {

            $table->id();

            /**
             * Header Training.
             */
            $table->foreignId('forecast_training_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            /**
             * Ranking hasil Grid Search.
             */
            $table->unsignedInteger('rank')
                ->nullable();
                
            $table->unsignedInteger('iteration');
            /**
             * Parameter Holt.
             */
            $table->decimal('alpha', 4, 2);

            $table->decimal('beta', 4, 2);
            
            $table->string('metric',20);
            /**
             * Nilai evaluasi.
             */
            $table->decimal('mae', 15, 6);

            $table->decimal('mape', 15, 6);

            $table->decimal('rmse', 15, 6);

            $table->decimal('sse', 15, 6);

            /**
             * Parameter terbaik?
             */
            $table->boolean('is_best')
                ->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'forecast_training_id',
                'rank',
            ]);

            $table->index([
                'forecast_training_id',
                'is_best',
            ]);

            $table->index([
                'alpha',
                'beta',
            ]);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_grid_searches');
    }
};