<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use App\Models\Forecast\ForecastTraining;
use App\Models\Forecast\ForecastTrainingResult;
use App\Models\Master\Product;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class ForecastTrainingHistory extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable, HasPageShield;

    protected string $view = 'filament.pages.forecast-training-history';

    protected static ?string $title = 'Riwayat Training';

    protected static ?string $navigationLabel = 'Riwayat Training';

    protected static string|UnitEnum|null $navigationGroup = 'Model Forecast';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort    = 2;

    /**
     * Ringkasan per produk:
     * hanya ambil training aktif atau training terbaru per produk.
     */
    public function getSummaryPerProduct(): \Illuminate\Support\Collection
    {
        return ForecastTraining::query()
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

                $totalTrainings = ForecastTraining::query()
                    ->where('product_id', $training->product_id)
                    ->where('granularity', $training->granularity)
                    ->count();

                return [

                    'product_id'      => $training->product_id,

                    'product_name'    => $training->product->name,

                    'product_code'    => $training->product->code,

                    'granularity'     => ucfirst($training->granularity),

                    'total_trainings' => $totalTrainings,

                    'has_active'      => true,

                    'active_alpha'    => $training->alpha,

                    'active_beta'     => $training->beta,

                    'active_mape'     => $training->mape,

                    'active_mae'      => $training->mae,

                    'active_rmse'     => $training->rmse,

                    'active_granularity' => ucfirst($training->granularity),

                    'active_trained_at' => $training->trained_at?->format('d M Y H:i'),

                    'latest_trained_at' => $training->trained_at?->format('d M Y H:i'),

                ];

            })
            ->values();
    }

    /**
     * Tabel detail semua riwayat training.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                ForecastTraining::query()
                    ->with('product')
                    ->latest()
            )
            ->columns([
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('granularity')
                    ->label('Granularity')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'monthly' => 'info',
                        'weekly'  => 'warning',
                        default   => 'gray',
                    }),

                TextColumn::make('alpha')
                    ->label('Alpha (α)')
                    ->numeric(2)
                    ->alignCenter(),

                TextColumn::make('beta')
                    ->label('Beta (β)')
                    ->numeric(2)
                    ->alignCenter(),

                TextColumn::make('mape')
                    ->label('MAPE (%)')
                    ->numeric(2)
                    ->suffix('%')
                    ->alignEnd()
                    ->color(fn($state) => match(true) {
                        $state === null        => 'gray',
                        (float)$state < 10    => 'success',
                        (float)$state < 20    => 'warning',
                        default               => 'danger',
                    }),

                TextColumn::make('mae')
                    ->label('MAE')
                    ->numeric(2)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rmse')
                    ->label('RMSE')
                    ->numeric(2)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sse')
                    ->label('SSE')
                    ->numeric(2)
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('training_records')
                    ->label('Data Training')
                    ->suffix(' periode')
                    ->alignCenter(),

                TextColumn::make('testing_records')
                    ->label('Data Testing')
                    ->suffix(' periode')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('grid_search_total')
                    ->label('Grid Search')
                    ->suffix(' kombinasi')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'completed'  => 'success',
                        'processing' => 'warning',
                        'failed'     => 'danger',
                        default      => 'gray',
                    }),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Produk')
                    ->options(
                        Product::query()
                            ->whereHas('forecastTrainings')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    ),

                SelectFilter::make('granularity')
                    ->label('Granularity')
                    ->options([
                        'monthly' => 'Monthly',
                        'weekly'  => 'Weekly',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'completed'  => 'Completed',
                        'processing' => 'Processing',
                        'failed'     => 'Failed',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Status Aktif')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Tidak Aktif',
                    ]),
            ])
            ->defaultSort('id', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}