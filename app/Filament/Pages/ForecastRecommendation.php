<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use App\Models\Forecast\ForecastTraining;
use App\Models\Forecast\ForecastTrainingResult;
use App\Models\Master\Product;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Schema;

class ForecastRecommendation extends Page implements HasForms, HasTable
{
    use InteractsWithTable, InteractsWithForms, HasPageShield;

    protected static string|UnitEnum|null $navigationGroup = 'Prakiraan (Forecast)';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Wallet;

    protected static ?string $navigationLabel = 'Rekomendasi Stok';

    protected static ?string $title = 'Rekomendasi Penambahan Stok';

    protected static ?int $navigationSort = 2;
    
    protected string $view = 'filament.pages.forecast-recommendation';

    protected static ?string $pollingInterval = null;

    public ?int $product_id = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('goToProducts')
                ->label('Kelola Stok di Produk')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->color('success')
                ->url(fn() => url('backend/master/products')),
        ];
    }

    protected function getHeaderWidgets(): array { return []; }
    protected function getFooterWidgets(): array { return []; }

    public function form(Schema $form): Schema
    {
        return $form->schema([
            Select::make('product_id')
                ->label('Pilih Produk')
                ->options(Product::pluck('name', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state) {
                    $this->product_id = $state;
                    $this->resetTable();
                }),
        ]);
    }

    public function submit(): void {}

    public function getStats(): array
    {
        $product = Product::find($this->product_id);
        if (! $product) return [];

        $training = ForecastTraining::query()
            ->where('product_id', $this->product_id)
            ->where('is_active', true)
            ->first();

        if (! $training) {
            return [
                'name'          => $product->name,
                'stock'         => number_format($product->stock, 0),
                'status'        => 'Belum ada training aktif',
                'restock_month' => null,
                'is_aman'       => false,
            ];
        }

        /**
         * Hitung kumulatif per periode untuk cari
         * bulan pertama stok habis.
         */
        $futures = ForecastTrainingResult::query()
            ->where('forecast_training_id', $training->id)
            ->where('is_future', true)
            ->orderBy('period_date')
            ->get();

        $stock        = (float) $product->stock;
        $kumulatif    = 0;
        $restockMonth = null;

        foreach ($futures as $row) {
            $kumulatif += (float) $row->forecast;
            if ($stock - $kumulatif < 0 && $restockMonth === null) {
                // Bulan pertama stok diperkirakan habis
                $restockMonth = $row->period_date->format('F Y');
            }
        }

        $totalForecast = $futures->sum('forecast');
        $sisa          = $stock - $totalForecast;
        $safetyStock   = $totalForecast * 0.1; // 10% safety stock  
        $stockAmount   = abs($sisa) + $safetyStock; // Jumlah stok yang harus ditambahkan
        $isAman        = $sisa >= 0;


        $status = $isAman
            ? 'Aman'
            : 'Tambah Stok (' . number_format(abs($stockAmount), 0, ',', '.') . ')';

        return [
            'name'          => $product->name,
            'stock'         => number_format($stock, 0, ',', '.'),
            'status'        => $status,
            'restock_month' => $restockMonth,  // ← bulan pertama stok habis
            'is_aman'       => $isAman,
        ];
    }

    public function table(Table $table): Table
    {
        $productId  = $this->product_id;
        $trainingId = ForecastTraining::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->value('id');

        $stock = (float) (Product::find($productId)?->stock ?? 0);

        return $table
            ->query(
                ForecastTrainingResult::query()
                    ->when(
                        $trainingId,
                        fn($q) => $q->where('forecast_training_id', $trainingId),
                        fn($q) => $q->whereRaw('1 = 0')
                    )
                    ->where('is_future', true)
                    ->orderBy('period_date')
            )
            ->columns([
                TextColumn::make('period_date')
                    ->label('Periode')
                    ->date('M Y'),

                TextColumn::make('forecast')
                    ->label('Forecast (qty)')
                    ->formatStateUsing(fn($state) => number_format((float)$state, 0, ',', '.')),

                TextColumn::make('kumulatif')
                    ->label('Kumulatif Forecast')
                    ->getStateUsing(function ($record) use ($trainingId) {
                        return (float) ForecastTrainingResult::query()
                            ->where('forecast_training_id', $trainingId)
                            ->where('is_future', true)
                            ->where('period_date', '<=', $record->period_date)
                            ->sum('forecast');
                    })
                    ->formatStateUsing(fn($state) => number_format((float)$state, 0, ',', '.')),

                TextColumn::make('sisa_stok')
                    ->label('Estimasi Sisa Stok')
                    ->getStateUsing(function ($record) use ($trainingId, $stock) {
                        $kumulatif = (float) ForecastTrainingResult::query()
                            ->where('forecast_training_id', $trainingId)
                            ->where('is_future', true)
                            ->where('period_date', '<=', $record->period_date)
                            ->sum('forecast');
                        return $stock - $kumulatif;
                    })
                    ->formatStateUsing(fn($state) => number_format((float)$state, 0, ',', '.'))
                    ->color(fn($state) => (float)$state < 0 ? 'danger' : 'success'),

                TextColumn::make('status_periode')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record) use ($trainingId, $stock) {
                        $kumulatif = (float) ForecastTrainingResult::query()
                            ->where('forecast_training_id', $trainingId)
                            ->where('is_future', true)
                            ->where('period_date', '<=', $record->period_date)
                            ->sum('forecast');
                        return ($stock - $kumulatif) < 0 ? 'Tambah Stok' : 'Aman';
                    })
                    ->color(fn($state) => $state === 'Aman' ? 'success' : 'danger'),
            ])
            ->striped()
            ->paginated(false);
    }

    public function updatedProductId($value): void
    {
        $this->product_id = $value;
        $this->resetTable();
    }
}