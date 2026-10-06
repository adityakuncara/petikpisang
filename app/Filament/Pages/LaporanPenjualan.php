<?php

namespace App\Filament\Pages;

use App\Models\Master\Product;
use App\Models\Transaksi\Sale;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use UnitEnum;

class LaporanPenjualan extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.pages.laporan-penjualan';

    protected static ?string $title          = 'Laporan Penjualan';
    protected static ?string $navigationLabel = 'Laporan Penjualan';
    protected static string|UnitEnum|null $navigationGroup   = 'Transaksi';
    protected static string|BackedEnum|null $navigationIcon  = Heroicon::OutlinedDocumentChartBar;
    protected static ?int $navigationSort    = 2;

    public ?array $data = [
        'product_id' => null,
        'start_date' => null,
        'end_date'   => null,
    ];

    public bool $isFiltered = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderWidgets(): array { return []; }
    protected function getFooterWidgets(): array { return []; }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filter Laporan')
                    ->columns(3)
                    ->schema([
                        Select::make('product_id')
                            ->label('Produk')
                            ->options(Product::query()->orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Semua Produk')
                            ->searchable()
                            ->preload(),

                        DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('end_date')
                            ->label('Tanggal Akhir')
                            ->displayFormat('d/m/Y'),
                    ]),
            ]);
    }

    /**
     * Terapkan filter — dipanggil dari blade via wire:click.
     * TIDAK boleh dispatch event ke widget karena menyebabkan 419.
     */
    public function applyFilter(): void
    {
        $this->isFiltered = true;
        $this->resetTable();
    }

    /**
     * Reset semua filter.
     */
    public function resetFilter(): void
    {
        $this->isFiltered = false;
        $this->form->fill([
            'product_id' => null,
            'start_date' => null,
            'end_date'   => null,
        ]);
        $this->resetTable();
    }

    // ── CARD STATISTIK ──────────────────────────────────────────────

    public function getTopSellingProduct(): array
    {
        $result = Sale::query()
            ->selectRaw('product_id, SUM(quantity) as total_qty')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->with('product')
            ->first();

        return [
            'name'  => $result?->product?->name ?? '-',
            'value' => number_format((float)($result?->total_qty ?? 0), 0, ',', '.'),
        ];
    }

    public function getLowestSellingProduct(): array
    {
        $result = Sale::query()
            ->selectRaw('product_id, SUM(quantity) as total_qty')
            ->groupBy('product_id')
            ->orderBy('total_qty')
            ->with('product')
            ->first();

        return [
            'name'  => $result?->product?->name ?? '-',
            'value' => number_format((float)($result?->total_qty ?? 0), 0, ',', '.'),
        ];
    }

    public function getHighestTransaction(): array
    {
        $result = Sale::query()
            ->orderByDesc('total_revenue')
            ->with('product')
            ->first();

        return [
            'name'  => $result?->product?->name ?? '-',
            'value' => 'Rp ' . number_format((float)($result?->total_revenue ?? 0), 0, ',', '.'),
            'date'  => $result?->sale_date?->format('d M Y') ?? '-',
            'qty'   => number_format((float)($result?->quantity ?? 0), 0, ',', '.') . ' unit',
        ];
    }

    // ── RINGKASAN PER PRODUK ────────────────────────────────────────

    public function getSummaryByProduct(): \Illuminate\Support\Collection
    {
        if (! $this->isFiltered) return collect();

        $productId = $this->data['product_id'] ?? null;
        $startDate = $this->data['start_date'] ?? null;
        $endDate   = $this->data['end_date'] ?? null;

        return Sale::query()
            ->selectRaw('product_id, SUM(quantity) as total_qty, SUM(total_revenue) as total_revenue, COUNT(*) as total_transaksi')
            ->groupBy('product_id')
            ->with('product')
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->when($startDate, fn($q) => $q->whereDate('sale_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->whereDate('sale_date', '<=', $endDate))
            ->get()
            ->map(fn($r) => [
                'product_name'    => $r->product?->name ?? '-',
                'total_qty'       => number_format((float)$r->total_qty, 0, ',', '.'),
                'total_revenue'   => 'Rp ' . number_format((float)$r->total_revenue, 0, ',', '.'),
                'total_transaksi' => $r->total_transaksi,
            ]);
    }

    // ── TABEL DETAIL ────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        if (! $this->isFiltered) {
            return $table->query(Sale::query()->whereRaw('1 = 0'))->columns([]);
        }

        $productId = $this->data['product_id'] ?? null;
        $startDate = $this->data['start_date'] ?? null;
        $endDate   = $this->data['end_date'] ?? null;

        $subQuery = DB::table('sales')
            ->selectRaw('
                MIN(id) as id,
                YEAR(sale_date) as year,
                MONTH(sale_date) as month,
                product_id,
                SUM(quantity) as total_qty,
                SUM(total_revenue) as total_revenue
            ')
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->when($startDate, fn($q) => $q->whereDate('sale_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->whereDate('sale_date', '<=', $endDate))
            ->whereNull('deleted_at')
            ->groupBy('year', 'month', 'product_id');

        return $table
            ->query(Sale::query()->fromSub($subQuery, 'sales')->orderByDesc('year')->orderByDesc('month'))
            ->columns([
                TextColumn::make('periode')
                    ->label('Periode')
                    ->getStateUsing(fn($record) =>
                        Carbon::createFromDate($record->year, $record->month, 1)
                            ->translatedFormat('F Y')
                    ),

                TextColumn::make('product.name')
                    ->label('Produk')
                    ->sortable(),

                TextColumn::make('total_qty')
                    ->label('Total Qty')
                    ->formatStateUsing(fn($state) => number_format((float)$state, 0, ',', '.'))
                    ->alignEnd(),

                TextColumn::make('total_revenue')
                    ->label('Total Omzet')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format((float)$state, 0, ',', '.'))
                    ->alignEnd(),
            ])
            ->striped()
            ->paginated([15, 25, 50]);
    }

    // ── EXPORT EXCEL ────────────────────────────────────────────────

    /**
     * Export via response()->streamDownload() — aman dari 419.
     * Dipanggil dari blade via wire:click.
     */
    public function exportToExcel(): mixed
    {
        if (! $this->isFiltered) return null;

        $productId = $this->data['product_id'] ?? null;
        $startDate = $this->data['start_date'] ?? null;
        $endDate   = $this->data['end_date'] ?? null;
        $fileName  = 'Laporan_Penjualan_' . date('Ymd_His') . '.xlsx';

        // Simpan ke file temp dulu karena streamDownload + OpenSpout tidak bisa langsung ke php://output di Livewire
        $tempPath = storage_path('app/private/temp-exports/' . $fileName);
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer = new Writer(new Options());
        $writer->openToFile($tempPath);

        $headerRow = Row::fromValues([
            'Tanggal', 'Produk', 'Qty', 'Harga Satuan', 'Total Pendapatan', 'Catatan',
        ]);

        $baseQuery = Sale::query()
            ->with('product')
            ->when($startDate, fn($q) => $q->whereDate('sale_date', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->whereDate('sale_date', '<=', $endDate))
            ->orderBy('sale_date');

        // ── Semua produk → tiap produk satu sheet ──────────────────
        if (empty($productId)) {

            $products    = Product::orderBy('name')->get();
            $firstSheet  = true;

            foreach ($products as $product) {

                $sales = (clone $baseQuery)->where('product_id', $product->id)->get();
                if ($sales->isEmpty()) continue;

                $sheet = $firstSheet
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();

                $firstSheet = false;
                $sheet->setName(mb_substr($product->code ?? $product->name, 0, 31));

                // Info header sheet
                $writer->addRow(Row::fromValues(['Produk:', $product->name]));
                $writer->addRow(Row::fromValues([
                    'Periode:',
                    ($startDate ?? 'Semua') . ' s/d ' . ($endDate ?? 'Semua'),
                ]));
                $writer->addRow(Row::fromValues([]));
                $writer->addRow($headerRow);

                $totalQty = $totalRevenue = 0;
                foreach ($sales as $i => $sale) {
                    $writer->addRow(Row::fromValues([
                        $sale->sale_date?->format('d/m/Y') ?? '-',
                        $sale->product?->name ?? '-',
                        (float) $sale->quantity,
                        (float) $sale->unit_price,
                        (float) $sale->total_revenue,
                        $sale->notes ?? '-',
                    ]));
                    $totalQty     += (float) $sale->quantity;
                    $totalRevenue += (float) $sale->total_revenue;
                }

                // Baris total
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues(['', 'TOTAL', $totalQty, '', $totalRevenue, '']));
            }

        // ── Satu produk → sheet per tahun ──────────────────────────
        } else {

            $product  = Product::find($productId);
            $sales    = (clone $baseQuery)->where('product_id', $productId)->get();
            $byYear   = $sales->groupBy(fn($s) => $s->sale_date?->format('Y') ?? date('Y'));

            $firstSheet = true;
            foreach ($byYear as $year => $yearSales) {

                $sheet = $firstSheet
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();

                $firstSheet = false;
                $sheetName  = $year . ' - ' . mb_substr($product?->name ?? 'Produk', 0, 20);
                $sheet->setName(mb_substr($sheetName, 0, 31));

                $writer->addRow(Row::fromValues(['Produk:', $product?->name ?? '-']));
                $writer->addRow(Row::fromValues(['Tahun:', $year]));
                $writer->addRow(Row::fromValues([]));
                $writer->addRow($headerRow);

                $totalQty = $totalRevenue = 0;
                foreach ($yearSales as $sale) {
                    $writer->addRow(Row::fromValues([
                        $sale->sale_date?->format('d/m/Y') ?? '-',
                        $sale->product?->name ?? '-',
                        (float) $sale->quantity,
                        (float) $sale->unit_price,
                        (float) $sale->total_revenue,
                        $sale->notes ?? '-',
                    ]));
                    $totalQty     += (float) $sale->quantity;
                    $totalRevenue += (float) $sale->total_revenue;
                }

                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues(['', 'TOTAL', $totalQty, '', $totalRevenue, '']));
            }
        }

        $writer->close();

        return response()->streamDownload(function () use ($tempPath) {
            readfile($tempPath);
            @unlink($tempPath);
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}