<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\GranularityEnum;
use App\Exports\ForecastSalesExport;
use App\Models\Forecast\ForecastTraining;
use App\Models\Master\Product;
use App\Models\Transaksi\Sale;
use App\Services\Forecast\ForecastService;
use App\Support\Aggregator\SalesAggregator;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ForecastSales extends Page implements HasForms
{
    use InteractsWithForms, HasPageShield;

    protected string $view = 'filament.pages.forecast-sales';

    protected static ?string $title           = 'Forecast Penjualan';
    protected static ?string $navigationLabel  = 'Forecast Penjualan';
    protected static ?string $recordTitleAttribute = 'Forecast Penjualan';
    protected static string|UnitEnum|null   $navigationGroup = 'Prakiraan (Forecast)';
    protected static string|BackedEnum|null $navigationIcon  = Heroicon::OutlinedPresentationChartLine;
    protected static ?int $navigationSort = 1;

    // ── State ────────────────────────────────────────────────────────
    public ?array            $data             = [];
    public array             $forecast         = [];
    public ?ForecastTraining $training          = null;
    public array             $summary          = [];
    public array             $trainingForecast = [];
    public array             $futureForecast   = [];
    public bool              $showChart        = false;
    public int               $trainingPage     = 1;
    public int               $trainingPerPage  = 10;
    public ?string           $productName      = null;
    public ?int              $filterYear       = null;

    // ── Info data yang digunakan (ditampilkan di UI setelah generate) ─
    public ?string $dataRangeInfo = null;

    public function mount(): void
    {
        $this->form->fill([
            'product_id'  => null,
            'granularity' => GranularityEnum::MONTHLY->value,
            'future'      => 3,
            'data_years'  => null, // null = semua data
        ]);
    }

    // ── Form ─────────────────────────────────────────────────────────

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Forecast Penjualan')
                    ->description('Gunakan form ini untuk memilih produk dan rentang tanggal. ⚠ Pastikan Model Forecast produk sudah ditraining ⚠')
                    ->columns(4)
                    ->schema([

                        Select::make('product_id')
                            ->label('Produk')
                            ->options(Product::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages(['required' => 'Produk harus dipilih.']),

                        Select::make('granularity')
                            ->label('Granularity')
                            ->options(GranularityEnum::class)
                            ->default(GranularityEnum::MONTHLY->value)
                            ->required(),

                        Select::make('data_years')
                            ->label('Rentang Data')
                            ->placeholder('Semua Data')
                            ->options([
                                1 => '1 Tahun Terakhir',
                                2 => '2 Tahun Terakhir',
                                3 => '3 Tahun Terakhir',
                                4 => '4 Tahun Terakhir',
                                5 => '5 Tahun Terakhir',
                            ]),

                        TextInput::make('future')
                            ->label('Periode Prediksi')
                            ->numeric()
                            ->rules(['numeric', 'min:1', 'max:24'])
                            ->default(3)
                            ->minValue(1)
                            ->maxValue(24)
                            ->required()
                            ->validationMessages([
                                'required' => 'Harus diisi.',
                                'numeric'  => 'Harus berupa angka.',
                                'min'      => 'Minimal :min.',
                                'max'      => 'Maksimal :max.',
                            ]),

                    ])
                    ->footer('📌 Catatan: Untuk Weekly secara otomatis periode yang diambil adalah 24 bulan terakhir.'),
            ]);
    }

    // ── Generate ─────────────────────────────────────────────────────

    public function generate(): void
    {
        $this->form->getState();

        // Reset state
        $this->filterYear       = null;
        $this->trainingPage     = 1;
        $this->dataRangeInfo    = null;
        $this->forecast         = [];
        $this->summary          = [];
        $this->futureForecast   = [];
        $this->trainingForecast = [];

        $product = Product::findOrFail($this->data['product_id']);
        $this->productName = $product->name;

        $this->training = ForecastTraining::query()
            ->where('product_id', $product->id)
            ->where('granularity', $this->data['granularity'])
            ->where('is_active', true)
            ->first();

        if (! $this->training) {
            Notification::make()
                ->title('Training belum tersedia')
                ->body('Belum ada training aktif untuk produk ini dengan granularity ' . $this->data['granularity'] . '. Silakan jalankan training terlebih dahulu.')
                ->warning()
                ->send();
            return;
        }

        $granularity = GranularityEnum::from($this->data['granularity']);
        $dataYears   = $this->data['data_years'] ? (int) $this->data['data_years'] : null;

        /**
         * ── Rentang data berdasarkan pilihan tahun ──────────────────
         *
         * Jika data_years dipilih → ambil data dari N tahun terakhir
         * Jika tidak → ForecastService::run() ambil semua data (default)
         */
        if ($dataYears !== null) {

            // Cutoff: N tahun ke belakang dari awal bulan/minggu sekarang
            $cutoff = now()->subYears($dataYears)->startOfMonth();

            // Ambil data penjualan sesuai rentang
            $sales = Sale::query()
                ->where('product_id', $product->id)
                ->whereDate('sale_date', '>=', $cutoff)
                ->orderBy('sale_date')
                ->get();

            if ($sales->isEmpty()) {
                Notification::make()
                    ->title('Tidak ada data')
                    ->body("Tidak ada data penjualan dalam {$dataYears} tahun terakhir untuk produk ini.")
                    ->warning()
                    ->send();
                return;
            }

            // Agregasi manual menggunakan SalesAggregator
            $aggregator = app(SalesAggregator::class);
            $dataset    = $aggregator->aggregate($sales, $granularity);

            if ($dataset->count() < 2) {
                Notification::make()
                    ->title('Data tidak cukup')
                    ->body("Data dalam {$dataYears} tahun terakhir hanya menghasilkan {$dataset->count()} periode. Minimal 2 periode untuk menjalankan DES.")
                    ->warning()
                    ->send();
                return;
            }

            // Jalankan forecast dari dataset yang sudah difilter
            $result = app(ForecastService::class)->runFromDataset(
                product:     $product,
                dataset:     $dataset,
                alpha:       (float) $this->training->alpha,
                beta:        (float) $this->training->beta,
                granularity: $granularity,
                future:      (int) $this->data['future'],
            );

            // Info rentang data untuk ditampilkan di UI
            $this->dataRangeInfo = sprintf(
                '%d tahun terakhir (%s s/d %s) · %d periode',
                $dataYears,
                $cutoff->format('M Y'),
                now()->format('M Y'),
                $dataset->count()
            );

        } else {

            // Semua data — pakai ForecastService::run() standar
            $result = app(ForecastService::class)->run(
                product:     $product,
                granularity: $granularity,
                alpha:       (float) $this->training->alpha,
                beta:        (float) $this->training->beta,
                future:      (int)   $this->data['future'],
            );

            $this->dataRangeInfo = 'Semua data histori · ' . count($result['training']) . ' periode';
        }

        $this->forecast         = $result;
        $this->trainingForecast = collect($result['training'])->all();
        $this->futureForecast   = collect($result['future'])->all();

        $this->summary = [
            'alpha' => $this->training->alpha,
            'beta'  => $this->training->beta,
            'mae'   => $this->training->mae,
            'mape'  => $this->training->mape,
            'rmse'  => $this->training->rmse,
            'sse'   => $this->training->sse,
        ];
    }

    // ── Helper ────────────────────────────────────────────────────────

    private function isWeekly(): bool
    {
        return ($this->data['granularity'] ?? '') === GranularityEnum::WEEKLY->value;
    }

    private function periodLabel(Carbon $date): string
    {
        if ($this->isWeekly()) {
            return $date->translatedFormat('M') . '-MG' . str_pad((string)$date->isoWeek(), 2, '0', STR_PAD_LEFT);
        }
        return $date->translatedFormat('M Y');
    }

    // ── Filter & Paginasi ─────────────────────────────────────────────

    /**
     * Weekly  → 24 bulan terakhir dari sekarang.
     *           Periode index-0 (basis L₀/T₀) selalu disertakan.
     * Monthly → filter per tahun jika $filterYear dipilih.
     */
    public function getFilteredTrainingForecast(): array
    {
        if ($this->isWeekly()) {
            $cutoff   = now()->startOfWeek()->subMonths(24);
            $all      = collect($this->trainingForecast);
            $filtered = $all->filter(fn($row) => $row['period_date']->gte($cutoff));

            // Pastikan periode basis L₀/T₀ selalu ada
            $first = $all->first();
            if ($first && $filtered->isNotEmpty() && $filtered->first()['index'] !== 1) {
                $filtered = collect([$first])->merge($filtered);
            }

            return $filtered->values()->all();
        }

        if ($this->filterYear) {
            return collect($this->trainingForecast)
                ->filter(fn($row) => $row['period_date']->year === (int)$this->filterYear)
                ->values()
                ->all();
        }

        return $this->trainingForecast;
    }

    public function getAvailableYears(): array
    {
        return collect($this->trainingForecast)
            ->map(fn($row) => $row['period_date']->year)
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn($year) => [$year => (string)$year])
            ->toArray();
    }

    public function getTrainingForecastPaginated(): array
    {
        return collect($this->getFilteredTrainingForecast())
            ->forPage($this->trainingPage, $this->trainingPerPage)
            ->values()
            ->all();
    }

    public function getTrainingTotalPages(): int
    {
        return (int) ceil(count($this->getFilteredTrainingForecast()) / $this->trainingPerPage);
    }

    public function trainingPreviousPage(): void
    {
        if ($this->trainingPage > 1) $this->trainingPage--;
    }

    public function trainingNextPage(): void
    {
        if ($this->trainingPage < $this->getTrainingTotalPages()) $this->trainingPage++;
    }

    // ── Export ────────────────────────────────────────────────────────

    public function export(): mixed
    {
        if (empty($this->forecast)) {
            Notification::make()->title('Belum ada data')->body('Silakan generate forecast terlebih dahulu.')->warning()->send();
            return null;
        }

        $product = Product::find($this->data['product_id']);

        return (new ForecastSalesExport(
            summary:     $this->summary,
            training:    $this->trainingForecast,
            future:      $this->futureForecast,
            productName: $product?->name ?? '-',
            granularity: $this->data['granularity'],
        ))->download();
    }

    // ── Chart ─────────────────────────────────────────────────────────

    public function openChart(): void
    {
        $this->dispatch('open-modal', id: 'forecast-chart');
        $this->dispatch('chart-data', data: $this->getChartData());
    }

    public function closeChart(): void
    {
        $this->dispatch('close-modal', id: 'forecast-chart');
    }

    public function getChartData(): array
    {
        $filtered = $this->getFilteredTrainingForecast();
        $labels = $actual = $inSampleForecast = $level = $lower = $upper = $ape = [];

        foreach ($filtered as $row) {
            $labels[]           = $this->periodLabel(Carbon::parse($row['period_date']));
            $actual[]           = $row['actual']   !== null ? (float) $row['actual']   : null;
            $inSampleForecast[] = $row['forecast'] !== null ? (float) $row['forecast'] : null;
            $level[]            = $row['level']    !== null ? (float) $row['level']    : null;
            $lower[]            = null;
            $upper[]            = null;
            $ape[]              = $row['ape']      !== null ? (float) $row['ape']      : null;
        }

        foreach ($this->futureForecast as $row) {
            $labels[]           = $this->periodLabel(Carbon::parse($row['period_date']));
            $actual[]           = null;
            $inSampleForecast[] = $row['forecast'] !== null ? (float) $row['forecast'] : null;
            $level[]            = $row['level']    !== null ? (float) $row['level']    : null;
            $lower[]            = isset($row['interval_lower']) ? (float) $row['interval_lower'] : null;
            $upper[]            = isset($row['interval_upper']) ? (float) $row['interval_upper'] : null;
            $ape[]              = null;
        }

        return compact('labels', 'actual', 'level', 'lower', 'upper', 'ape')
            + ['forecast' => $inSampleForecast];
    }
}