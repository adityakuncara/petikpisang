<?php

declare(strict_types=1);

use App\Enums\GranularityEnum;
use App\Enums\TrainingStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('forecast_trainings', function (Blueprint $table) {

        $table->id();

        /**
         * Produk.
         */
        $table->foreignId('product_id')
            ->constrained()
            ->cascadeOnUpdate()
            ->restrictOnDelete();

        /**
         * Weekly / Monthly
         */
        $table->string('granularity', 20);

        /**
         * Parameter terbaik hasil Grid Search.
         */
        $table->decimal('alpha', 4, 2);

        $table->decimal('beta', 4, 2);

        /**
         * Persentase dataset.
         */
        $table->decimal('training_ratio', 5, 2);

        $table->decimal('testing_ratio', 5, 2);

        /**
         * Jumlah data.
         */
        $table->unsignedInteger('training_records');

        $table->unsignedInteger('testing_records');

        /**
         * Nilai evaluasi model.
         */
        $table->decimal('mae', 15, 6)->nullable();

        $table->decimal('mape', 15, 6)->nullable();

        $table->decimal('rmse', 15, 6)->nullable();

        $table->decimal('sse', 15, 6)->nullable();

        /**
         * Periode training.
         */
        $table->date('training_start_date');

        $table->date('training_end_date');

        /**
         * Total kombinasi Grid Search.
         */
        $table->unsignedInteger('grid_search_total')
            ->default(0);

        /**
         * Kombinasi terbaik.
         */
        $table->unsignedInteger('best_rank')
            ->nullable();

        /**
         * Training aktif.
         */
        $table->boolean('is_active')
            ->default(false);

        /**
         * Status.
         */
        $table->string('status', 20)
            ->default(TrainingStatusEnum::DRAFT->value);

        /**
         * Catatan.
         */
        $table->text('notes')->nullable();

        /**
         * User.
         */
        $table->foreignId('created_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        /**
         * Waktu training.
         */
        $table->timestamp('trained_at')
            ->nullable();

        $table->timestamps();

        $table->softDeletes();

        $table->index([
            'product_id',
            'granularity',
            'is_active',
        ]);

    });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_trainings');
    }
};