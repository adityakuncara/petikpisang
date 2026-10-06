<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    Schema::create('forecast_training_logs', function (Blueprint $table) {

        $table->id();

        $table->foreignId('forecast_training_id')
            ->constrained()
            ->cascadeOnDelete();

        /*
        |--------------------------------------------------------------------------
        | Progress
        |--------------------------------------------------------------------------
        */

        $table->unsignedTinyInteger('progress')
            ->default(0);

        /*
        |--------------------------------------------------------------------------
        | Tahapan
        |--------------------------------------------------------------------------
        */

        $table->string('step',100);

        /*
        |--------------------------------------------------------------------------
        | Pesan
        |--------------------------------------------------------------------------
        */
        $table->json('context')
            ->nullable();
        $table->string('message')
            ->nullable();

        /*
        |--------------------------------------------------------------------------
        | Grid Search
        |--------------------------------------------------------------------------
        */

        $table->integer('iteration')
            ->nullable();

        $table->integer('total_iteration')
            ->nullable();

        /*
        |--------------------------------------------------------------------------
        | Parameter Saat Ini
        |--------------------------------------------------------------------------
        */

        $table->decimal('alpha',5,2)
            ->nullable();

        $table->decimal('beta',5,2)
            ->nullable();

        /*
        |--------------------------------------------------------------------------
        | Metric Saat Ini
        |--------------------------------------------------------------------------
        */

        $table->decimal('metric_value',15,6)
            ->nullable();

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        $table->string('status',30)
            ->default('running');

        $table->timestamps();

    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_training_logs');
    }
};
