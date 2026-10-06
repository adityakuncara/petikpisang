<?php

namespace App\Filament\Resources\Transaksi\Sales\Pages;

use App\Filament\Resources\Transaksi\Sales\SaleResource;
use App\Models\Master\Product;
use App\Models\Transaksi\Sale;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Common\Entity\Row;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [

            // ────────────────────────────────────────────────────────
            // IMPOR EXCEL — menggunakan FastExcel
            // ────────────────────────────────────────────────────────
            Action::make('importExcel')
                ->label('Impor dari Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->form([
                    FileUpload::make('excel_file')
                        ->label('Pilih Berkas Excel (.xlsx)')
                        ->placeholder('Unggah file .xlsx di sini')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('temp-imports')
                        ->visibility('private')
                        ->required(),
                ])
                ->action(function (Action $action, array $data) {

                    // ── 1. Resolve path file ────────────────────────
                    $relativePath = is_array($data['excel_file'])
                        ? $data['excel_file'][0]
                        : $data['excel_file'];

                    $filePath = Storage::disk('local')->path($relativePath);

                    if (! file_exists($filePath)) {
                        Notification::make()
                            ->danger()
                            ->title('Berkas tidak ditemukan')
                            ->body('Path: ' . $filePath)
                            ->persistent()
                            ->send();
                        return;
                    }

                    $successCount = 0;
                    $errorRows    = [];
                    $rowNumber    = 1; // baris 1 = header, data mulai baris 2

                    try {

                        DB::transaction(function () use (
                            $filePath,
                            &$successCount,
                            &$errorRows,
                            &$rowNumber
                        ) {
                            // ── 2. FastExcel baca file, auto-map header ──
                            // FastExcel otomatis baca baris pertama sebagai key,
                            // mengembalikan Collection of array per baris.
                            (new FastExcel)
                                ->import($filePath, function (array $dataRow) use (
                                    &$successCount,
                                    &$errorRows,
                                    &$rowNumber
                                ) {
                                    $rowNumber++;

                                    // Trim semua key dan value
                                    $dataRow = array_map(
                                        fn($v) => is_string($v) ? trim($v) : $v,
                                        $dataRow
                                    );

                                    // ── 3. Skip baris kosong ───────────
                                    if (empty(array_filter($dataRow))) return null;

                                    // ── 4. Identifikasi produk ─────────
                                    $identifier = (string)(
                                        $dataRow['product_name']
                                        ?? $dataRow['product_id']
                                        ?? ''
                                    );

                                    if ($identifier === '') {
                                        $errorRows[] = "Baris {$rowNumber}: Kolom produk kosong.";
                                        return null;
                                    }

                                    $product = Product::where('name', $identifier)->first()
                                        ?? Product::where('code', $identifier)->first()
                                        ?? (is_numeric($identifier) ? Product::find((int)$identifier) : null);

                                    if (! $product) {
                                        $errorRows[] = "Baris {$rowNumber}: Produk '{$identifier}' tidak ditemukan.";
                                        return null;
                                    }

                                    // ── 5. Parse tanggal ───────────────
                                    // FastExcel sudah otomatis convert sel
                                    // bertipe date menjadi Carbon/DateTime.
                                    $saleDate = $this->parseSaleDate(
                                        $dataRow['sale_date'] ?? null,
                                        $rowNumber,
                                        $errorRows
                                    );
                                    if ($saleDate === null) return null;

                                    // ── 6. Validasi quantity ───────────
                                    $qty = (int)($dataRow['quantity'] ?? 0);
                                    if ($qty < 1) {
                                        $errorRows[] = "Baris {$rowNumber}: Quantity minimal 1 (diterima: {$qty}).";
                                        return null;
                                    }

                                    // ── 7. Validasi stok ───────────────
                                    $product->refresh();
                                    if ($product->stock < $qty) {
                                        $errorRows[] = "Baris {$rowNumber}: Stok '{$product->name}' tidak mencukupi "
                                            . "(tersedia: {$product->stock}, diminta: {$qty}).";
                                        return null;
                                    }

                                    // ── 8. Hitung harga ────────────────
                                    $unitPrice = ! empty($dataRow['unit_price'])
                                        ? (float)$dataRow['unit_price']
                                        : (float)$product->selling_price;

                                    $totalRevenue = ! empty($dataRow['total_revenue'])
                                        ? (float)$dataRow['total_revenue']
                                        : ($unitPrice * $qty);

                                    // ── 9. Simpan Sale ─────────────────
                                    // SaleObserver::created() otomatis kurangi stok
                                    // via StockService::decrease() — JANGAN decrement manual
                                    Sale::create([
                                        'product_id'    => $product->id,
                                        'sale_date'     => $saleDate,
                                        'quantity'      => $qty,
                                        'unit_price'    => $unitPrice,
                                        'total_revenue' => $totalRevenue,
                                        'notes'         => $dataRow['notes'] ?? null,
                                        'created_by'    => auth()->id(),
                                    ]);

                                    $successCount++;

                                    return null; // FastExcel: return null jika tidak perlu collect hasil
                                });

                            // Rollback jika ada baris bermasalah
                            if (! empty($errorRows)) {
                                throw new \Exception(implode("\n", $errorRows));
                            }
                        });

                        // Hapus file temp setelah sukses
                        Storage::disk('local')->delete($relativePath);

                        Notification::make()
                            ->success()
                            ->title('Impor Berhasil')
                            ->body("{$successCount} data penjualan berhasil diimpor.")
                            ->send();

                    } catch (\Exception $e) {

                        // Hapus file temp meski gagal
                        Storage::disk('local')->delete($relativePath);

                        Notification::make()
                            ->danger()
                            ->title('Impor Gagal')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                }),

            // ────────────────────────────────────────────────────────
            // UNDUH TEMPLATE — tetap pakai OpenSpout
            // ────────────────────────────────────────────────────────
            Action::make('downloadTemplate')
                ->label('Unduh Template Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn() => $this->downloadTemplate()),
        ];
    }

    /**
     * Parse tanggal dari berbagai kemungkinan tipe:
     * - Carbon / DateTime object (FastExcel auto-convert)
     * - Angka serial Excel
     * - String berbagai format
     */
    protected function parseSaleDate(mixed $raw, int $rowNumber, array &$errorRows): ?string
    {
        if ($raw === null || $raw === '') {
            return now()->format('Y-m-d');
        }

        try {
            // FastExcel biasanya sudah convert ke Carbon/DateTime
            if ($raw instanceof \DateTimeInterface) {
                return Carbon::instance($raw)->format('Y-m-d');
            }

            // Angka serial Excel
            if (is_numeric($raw) && (int)$raw > 40000) {
                return Carbon::createFromTimestamp(((int)$raw - 25569) * 86400)
                    ->format('Y-m-d');
            }

            // String — coba berbagai format umum
            $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d', 'd M Y', 'd F Y'];
            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, trim((string)$raw))->format('Y-m-d');
                } catch (\Exception) {
                    // lanjut format berikutnya
                }
            }

            // Fallback: Carbon::parse
            return Carbon::parse((string)$raw)->format('Y-m-d');

        } catch (\Exception) {
            $errorRows[] = "Baris {$rowNumber}: Format tanggal '{$raw}' tidak dikenali.";
            return null;
        }
    }

    /**
     * Download template Excel — pakai OpenSpout.
     * 2 sheet:
     * - Sheet 1 "Template"      : header + contoh baris
     * - Sheet 2 "Daftar Produk" : referensi produk aktif
     */
    protected function downloadTemplate(): StreamedResponse
    {
        $products = Product::select('id', 'code', 'name', 'selling_price', 'stock')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->stream(function () use ($products) {

            $writer = new Writer(new Options());
            $writer->openToFile('php://output');

            // ── Sheet 1: Template ─────────────────────────────────
            $sheet1 = $writer->getCurrentSheet();
            $sheet1->setName('Template');

            $writer->addRow(Row::fromValues([
                'product_name',
                'sale_date',
                'quantity',
                'unit_price',
                'notes',
            ]));

            // Contoh baris agar user paham format
            $writer->addRow(Row::fromValues([
                'Nama / Kode Produk',
                date('Y-m-d'),
                10,
                '',
                'Catatan opsional',
            ]));

            // ── Sheet 2: Daftar Produk ────────────────────────────
            $sheet2 = $writer->addNewSheetAndMakeItCurrent();
            $sheet2->setName('Daftar Produk');

            $writer->addRow(Row::fromValues([
                'ID', 'Kode', 'Nama Produk', 'Harga Jual', 'Stok Saat Ini',
            ]));

            foreach ($products as $p) {
                $writer->addRow(Row::fromValues([
                    $p->id,
                    $p->code,
                    $p->name,
                    (float)$p->selling_price,
                    (float)$p->stock,
                ]));
            }

            $writer->close();

        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template_penjualan_' . date('Ymd') . '.xlsx"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }
}