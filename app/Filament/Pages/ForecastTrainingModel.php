<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use App\Enums\GranularityEnum;
use App\Models\Master\Product;
use App\Models\Forecast\ForecastTraining;
use App\Jobs\TrainForecastJob;
use Filament\Pages\Page;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Cache;

class ForecastTrainingModel extends Page implements HasForms
{
    use InteractsWithForms, HasPageShield;

    protected string $view = 'filament.pages.forecast-training-model';

    protected static ?string $title = 'Training Model';

    protected static ?string $navigationLabel = 'Training Model';

    protected static ?string $recordTitleAttribute = 'Training Model';

    protected static string|UnitEnum|null $navigationGroup = 'Model Forecast';
    
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    // ── State Progress ──────────────────────────────────────────────
    public bool   $showProgressModal = false;
    public float  $progress          = 0;
    public string $statusMessage     = '';
    public bool   $trainingComplete  = false;
    public ?array $trainingResult    = null;
    public ?string $trainingId       = null;
    public bool   $trainingFailed    = false;

    public function mount(): void
    {
        $this->form->fill([
            'granularity'    => GranularityEnum::MONTHLY->value,
            'training_mode'  => 'all',
            'training_ratio' => 80,
            'testing_ratio'  => 20,
            'future'         => 3,
            'alpha_start'    => 0.10,
            'alpha_end'      => 0.90,
            'beta_start'     => 0.10,
            'beta_end'       => 0.90,
            'step'           => 0.10,
            'metric'         => 'mape',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('train')
                ->label('Training Model')
                ->icon(Heroicon::OutlinedCpuChip)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Training Forecast Model')
                ->modalDescription('Training akan menjalankan Grid Search untuk mencari kombinasi Alpha dan Beta terbaik. Proses ini membutuhkan beberapa saat tergantung jumlah data dan kombinasi parameter.')
                ->modalSubmitActionLabel('Mulai Training')
                ->action(fn() => $this->startTraining()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([

                Section::make('Forecast Training')
                    ->columns(2)
                    ->description('📌 Untuk Granularity Weekly gunakan N-Periode Terakhir Max. 24 Bulan')
                    ->schema([

                        Select::make('product_id')
                            ->label('Produk')
                            ->options(Product::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('granularity')
                            ->label('Granularity')
                            ->options(GranularityEnum::class)
                            ->live()
                            ->required(),

                        Radio::make('training_mode')
                            ->label('Mode Training')
                            ->options([
                                'all'   => 'Semua Data',
                                'range' => 'Rentang Waktu',
                                'last'  => 'N Periode Terakhir',
                            ])
                            ->disabled(fn($get) => $get('granularity') === GranularityEnum::WEEKLY->value)
                            ->inline()
                            ->live()
                            ->columnSpanFull(),

                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->maxDate(now())
                            ->visible(fn($get) => $get('training_mode') === 'range'),

                        DatePicker::make('end_date')
                            ->label('Tanggal Akhir')
                            ->maxDate(now())
                            ->visible(fn($get) => $get('training_mode') === 'range'),

                        Select::make('last_period')
                            ->label('Periode Terakhir')
                            ->options(fn($get) =>
                                $get('granularity') === GranularityEnum::WEEKLY->value
                                    ? ['24' => '24 Minggu']
                                    : ['12' => '12 Bulan', '24' => '24 Bulan', '36' => '36 Bulan', '48' => '48 Bulan', '60' => '60 Bulan']
                            )
                            ->visible(fn($get) => $get('training_mode') === 'last')
                            ->required(fn($get) => $get('training_mode') === 'last'),
                    ]),

                Section::make('Training Configuration')
                    ->columns(4)
                    ->description('📌 Hanya bisa ubah horizon dan metric saja.')
                    ->schema([

                        TextInput::make('training_ratio')
                            ->label('Training Ratio (%)')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('testing_ratio')
                            ->label('Testing Ratio (%)')
                            ->numeric()
                            ->readOnly(),

                        TextInput::make('future')
                            ->label('Horizon (Periode)')
                            ->numeric()
                            ->required(),

                        Select::make('metric')
                            ->label('Metric Optimasi')
                            ->options([
                                'wmape' => 'WMAPE',
                                'mape'  => 'MAPE',
                                'mae'   => 'MAE',
                                'rmse'  => 'RMSE',
                                'sse'   => 'SSE',
                            ])
                            ->required(),
                    ]),

                Section::make('Grid Search Parameter')
                    ->columns(5)
                    ->description('📌 Untuk 🅰 dan 🅱, Minimal 0.05 dan Maksimal 0.95')
                    ->schema([

                        TextInput::make('alpha_start')
                            ->label('Alpha Start')
                            ->step(0.05)
                            ->type('number')
                            ->inputMode('decimal'),

                        TextInput::make('alpha_end')
                            ->label('Alpha End')
                            ->step(0.05)
                            ->type('number')
                            ->inputMode('decimal'),

                        TextInput::make('beta_start')
                            ->label('Beta Start')
                            ->step(0.05)
                            ->type('number')
                            ->inputMode('decimal'),

                        TextInput::make('beta_end')
                            ->label('Beta End')
                            ->step(0.05)
                            ->type('number')
                            ->inputMode('decimal'),

                        TextInput::make('step')
                            ->label('Step')
                            ->step(0.05)
                            ->type('number')
                            ->inputMode('decimal'),
                    ]),
            ]);
    }

    /**
     * Validasi sebelum training dimulai.
     */
    protected function validateTraining(): bool
    {
        $data = $this->data;

        if (blank($data['product_id'])) {
            Notification::make()->title('Produk belum dipilih.')->danger()->send();
            return false;
        }

        if ($data['training_mode'] === 'range') {
            if (blank($data['start_date'] ?? null) || blank($data['end_date'] ?? null)) {
                Notification::make()->title('Rentang tanggal belum lengkap.')->danger()->send();
                return false;
            }
            // Validasi khusus weekly: max 24 bulan
            if ($data['granularity'] === GranularityEnum::WEEKLY->value) {
                $diff = \Carbon\Carbon::parse($data['start_date'])->diffInMonths($data['end_date']);
                if ($diff > 24) {
                    Notification::make()->title('Rentang untuk Weekly maksimal 24 bulan.')->danger()->send();
                    return false;
                }
            }
        }

        if ($data['training_mode'] === 'last' && blank($data['last_period'] ?? null)) {
            Notification::make()->title('Jumlah periode terakhir belum dipilih.')->danger()->send();
            return false;
        }

        // Weekly tidak boleh "Semua Data"
        if ($data['granularity'] === GranularityEnum::WEEKLY->value && $data['training_mode'] === 'all') {
            Notification::make()->title('Granularity Weekly tidak bisa menggunakan "Semua Data".')->danger()->send();
            return false;
        }

        return true;
    }

    /**
     * Mulai proses training via queue.
     */
    public function startTraining(): void
    {
        if (! $this->validateTraining()) return;

        $product = Product::findOrFail($this->data['product_id']);

        // Generate ID unik untuk tracking progress
        $this->trainingId = uniqid('train_', true);

        // Reset state modal
        $this->progress         = 0;
        $this->statusMessage    = 'Memulai training...';
        $this->trainingComplete = false;
        $this->trainingResult   = null;
        $this->showProgressModal = true;

        // Simpan progress awal ke cache
        Cache::put("training_progress_{$this->trainingId}", [
            'progress' => 0,
            'status'   => 'Memulai training...',
            'complete' => false,
            'result'   => null,
        ], 3600);

        // Buka modal
        $this->dispatch('open-modal', id: 'training-progress');

        // Dispatch job ke queue
        TrainForecastJob::dispatch(
            productId:    $product->id,
            granularity:  $this->data['granularity'],
            trainingRatio:(float) $this->data['training_ratio'],
            testingRatio: (float) $this->data['testing_ratio'],
            alphaStart:   (float) $this->data['alpha_start'],
            alphaEnd:     (float) $this->data['alpha_end'],
            betaStart:    (float) $this->data['beta_start'],
            betaEnd:      (float) $this->data['beta_end'],
            step:         (float) $this->data['step'],
            future:       (int)   $this->data['future'],
            metric:       $this->data['metric'],
            trainingMode: $this->data['training_mode'],
            startDate:    $this->data['start_date'] ?? null,
            endDate:      $this->data['end_date'] ?? null,
            lastPeriod:   $this->data['last_period'] ?? null,
            userId:       auth()->id(),
            trainingId:   $this->trainingId
        );

        Notification::make()
            ->title('Training dimulai')
            ->body('Proses berjalan di latar belakang. Pantau progres pada modal.')
            ->info()
            ->send();
    }

    /**
     * Polling setiap 2 detik untuk update progress dari cache.
     */
    public function pollTrainingStatus(): void
    {
        if (! $this->trainingId) return;

        $cached = Cache::get("training_progress_{$this->trainingId}");
        if (! $cached) return;

        // Animasi halus: naikkan bertahap jika selisih besar
        $target = (float) $cached['progress'];
        if ($target > $this->progress) {
            $step = max(1, ceil(($target - $this->progress) / 3));
            $this->progress = min($target, $this->progress + $step);
        } else {
            $this->progress = $target;
        }

        $this->statusMessage    = $cached['status'];
        $this->trainingComplete = $cached['complete'];
        $this->trainingFailed   = $cached['failed'] ?? false;

        if ($cached['complete'] && $cached['result']) {
            $this->trainingResult = $cached['result'];
            $this->progress       = 100;

            if (! $this->trainingFailed) {
                $this->progress = 100;
            }
        }
    }

    /**
     * Tutup modal dan bersihkan cache.
     */
    public function closeProgressModal(): void
    {
        $this->showProgressModal = false;
        $this->dispatch('close-modal', id: 'training-progress');

        if ($this->trainingId) {
            Cache::forget("training_progress_{$this->trainingId}");
        }

        $this->trainingId       = null;
        $this->progress         = 0;
        $this->statusMessage    = '';
        $this->trainingComplete = false;
        $this->trainingFailed   = false;
        $this->trainingResult   = null;
    }
}