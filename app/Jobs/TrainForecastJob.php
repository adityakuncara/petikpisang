<?php

namespace App\Jobs;

use App\Enums\GranularityEnum;
use App\Models\Master\Product;
use App\Services\Forecast\TrainingService;
use App\Support\Aggregator\SalesAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class TrainForecastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout job: 10 menit
     */
    public int $timeout = 600;

    public function __construct(
        protected int     $productId,
        protected string  $granularity,
        protected float   $trainingRatio,
        protected float   $testingRatio,
        protected float   $alphaStart,
        protected float   $alphaEnd,
        protected float   $betaStart,
        protected float   $betaEnd,
        protected float   $step,
        protected int     $future,
        protected string  $metric,
        protected string  $trainingMode,
        protected ?string $startDate,
        protected ?string $endDate,
        protected ?string $lastPeriod,
        protected ?int    $userId,
        protected string  $trainingId,
    ) {}

    public function handle(TrainingService $trainingService): void
    {
        try {

            $this->updateProgress(5, 'Menyiapkan data...');

            $product     = Product::findOrFail($this->productId);
            $granularity = GranularityEnum::from($this->granularity);

            // ── Load dataset sesuai mode ────────────────────────────
            $dataset = $this->loadDataset($product, $granularity);

            $this->updateProgress(10, 'Data siap. Memulai Grid Search...');

            // ── Jalankan training ───────────────────────────────────
            $training = $trainingService->train(
                product:       $product,
                granularity:   $granularity,
                trainingRatio: $this->trainingRatio,
                testingRatio:  $this->testingRatio,
                alphaStart:    $this->alphaStart,
                alphaEnd:      $this->alphaEnd,
                betaStart:     $this->betaStart,
                betaEnd:       $this->betaEnd,
                step:          $this->step,
                future:        $this->future,
                metric:        $this->metric,
            );

            $this->updateProgress(95, 'Menyimpan hasil...');

            // ── Simpan hasil ke cache ───────────────────────────────
            $this->updateProgress(100, 'Selesai!', complete: true, result: [
                'alpha'     => $training->alpha,
                'beta'      => $training->beta,
                'mae'       => $training->mae,
                'mape'      => $training->mape,
                'rmse'      => $training->rmse,
                'sse'       => $training->sse,
                'status'    => $training->status,
                'is_active' => $training->is_active,
                'grid_search_total' => $training->grid_search_total,
                'best_rank'         => $training->best_rank,
            ]);

        } catch (\Throwable $e) {

            $this->updateProgress(0, ' Training Gagal : ' . $e->getMessage(), complete: true, result: null, failed: true);

        }
    }

    /**
     * Load dataset penjualan sesuai mode training.
     */
    protected function loadDataset(Product $product, GranularityEnum $granularity): \Illuminate\Support\Collection
    {
        $aggregator = app(SalesAggregator::class);

        $query = $product->sales()->orderBy('sale_date');

        match ($this->trainingMode) {
            'range' => $query->whereBetween('sale_date', [$this->startDate, $this->endDate]),
            'last'  => $query->latest('sale_date')->limit((int) $this->lastPeriod),
            default => null, // 'all' — tidak perlu filter
        };

        $sales = $query->get();

        if ($sales->isEmpty()) {
            throw new \Exception('Tidak ada data penjualan untuk kriteria yang dipilih.');
        }

        return $aggregator->aggregate($sales, $granularity);
    }

    /**
     * Update progress di cache.
     */
    protected function updateProgress(
        int     $progress,
        string  $status,
        bool    $complete = false,
        ?array  $result   = null,
        bool    $failed   = false,
    ): void {
        Cache::put("training_progress_{$this->trainingId}", [
            'progress' => $progress,
            'status'   => $status,
            'complete' => $complete,
            'failed'   => $failed,
            'result'   => $result,
        ], 3600);
    }
}