<?php

namespace App\Filament\Widgets;

use App\Models\Forecast\ForecastTraining;
use App\Models\Master\Product;
use Filament\Widgets\Widget;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductTrainingSummary extends Widget
{
    protected string $view = 'filament.widgets.product-training-summary';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public int $perPage = 2;
    public int $page = 1;

    public function getPaginatedSummaries()
    {
        $data = ForecastTraining::query()
            ->with('product')
            ->where('is_active', true)
            ->orderBy('product_id')
            ->orderByRaw("
                CASE granularity
                    WHEN 'monthly' THEN 1
                    WHEN 'weekly' THEN 2
                    ELSE 99
                END
            ")
            ->get()
            ->map(function (ForecastTraining $training) {

                return [

                    'training_id' => $training->id,

                    'product_id' => $training->product_id,

                    'product_name' => $training->product->name,

                    'product_code' => $training->product->code,

                    'granularity' => ucfirst($training->granularity),

                    'has_active' => true,

                    'active_alpha' => $training->alpha,

                    'active_beta' => $training->beta,

                    'active_mape' => $training->mape,

                    'active_mae' => $training->mae,

                    'active_rmse' => $training->rmse,

                    'active_trained_at' => $training->trained_at?->format('d M Y H:i'),

                ];

            })
            ->values();

        return new LengthAwarePaginator(

            $data->forPage(
                $this->page,
                $this->perPage
            ),

            $data->count(),

            $this->perPage,

            $this->page

        );
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }
}