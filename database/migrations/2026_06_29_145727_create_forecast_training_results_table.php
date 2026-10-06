<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_training_results', function (Blueprint $table) {

            $table->id();

            $table->foreignId('forecast_training_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            /**
             * Urutan data.
             */
            $table->unsignedInteger('sequence');

            /**
             * Label periode.
             */
            $table->string('period', 30);

            /**
             * Awal periode.
             */
            $table->date('period_date');

            /**
             * Rentang periode.
             */
            $table->date('start_date');

            $table->date('end_date');

            /**
             * Data aktual.
             */
            $table->decimal('actual', 15, 3)->nullable();

            /**
             * Forecast.
             */
            $table->decimal('forecast', 15, 3)->nullable();

            /**
             * Holt.
             */
            $table->decimal('level', 15, 6)->nullable();

            $table->decimal('trend', 15, 6)->nullable();

            /**
             * Error.
             */
            $table->decimal('error', 15, 6)->nullable();

            $table->decimal('absolute_error', 15, 6)->nullable();

            $table->decimal('squared_error', 15, 6)->nullable();

            $table->decimal('ape', 15, 6)->nullable();

            /**
             * Future Forecast?
             */
            $table->boolean('is_future')
                ->default(false);

            $table->timestamps();

            $table->index([
                'forecast_training_id',
                'sequence',
            ]);

            $table->index([
                'forecast_training_id',
                'period_date',
            ]);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'forecast_training_results'
        );
    }
};