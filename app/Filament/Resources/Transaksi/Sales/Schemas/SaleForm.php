<?php

namespace App\Filament\Resources\Transaksi\Sales\Schemas;

use App\Models\Master\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use App\Support\Formatter;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Produk')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                        // 1. Jika produk dikosongkan, reset semua nilai terkait
                        if (! $state) {
                            $set('unit_price', 0);
                            $set('total_revenue', 0);
                            return;
                        }

                        $product = Product::find($state);
                        if (! $product) return;

                        // 2. Set harga satuan otomatis dari database produk
                        $price = (float) $product->selling_price;
                        $set('unit_price', $price);

                        // 3. HITUNG OTOMATIS: Jika quantity sudah diisi duluan, langsung kalikan
                        $qty = (float) $get('quantity') ?? 0;
                        $set('total_revenue', $price * $qty);
                    }),

                DatePicker::make('sale_date')
                    ->label('Tanggal Penjualan')
                    ->date('d M Y')
                    ->default(now())
                    ->required(),

                TextInput::make('quantity')
                    ->label('Jumlah')
                    ->rules(['integer', 'min:1'])
                    ->required()
                    ->live(debounce: 500) // Beri sedikit debounce agar tidak terlalu membebani query database saat mengetik
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        // HITUNG OTOMATIS: Ambil harga satuan saat ini, kalikan dengan quantity baru
                        $price = (float) $get('unit_price') ?? 0;
                        $qty = (float) $state;
                        $set('total_revenue', $price * $qty);
                    })
                    ->rules([
                        static function (Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                $productId = $get('product_id');
                                if (! $productId) return;

                                $product = \App\Models\Master\Product::find($productId);
                                if (! $product) return;

                                // Hitung batas maksimal stok yang boleh diinput
                                $currentStock = (float) $product->stock;
                                
                                // Jika dalam mode EDIT, kita harus menambahkan kembali quantity yang sedang berjalan
                                // karena di database stoknya sudah terlanjur dikurangi oleh data transaksi ini.
                                $saleId = $get('../../record'); // Mengambil ID dari record modal edit Filament jika ada
                                if ($saleId) {
                                    $sale = \App\Models\Transaksi\Sale::find($saleId);
                                    if ($sale && (int)$sale->product_id === (int)$productId) {
                                        $currentStock += (float) $sale->quantity;
                                    }
                                }

                                if ($value > $currentStock) {
                                    $fail("Stok \"{$product->name}\" tidak mencukupi. Tersedia " . number_format($currentStock, 3) . ".");
                                }
                            };
                        },
                    ]),

                TextInput::make('unit_price')
                    ->label('Harga Satuan')
                    ->disabled()
                    ->readOnly()
                    ->dehydrated() // Memastikan nilai terkirim saat disubmit ke database
                    ->prefix('Rp')
                    ->default(0.0)
                    ->numeric(),

                // 5. Hidden field penampung nilai database agar reaktif secara live
                Hidden::make('total_revenue')
                    ->default(0)
                    ->live(), // Tambahkan live() agar Placeholder di bawahnya langsung mendeteksi perubahan state

                Placeholder::make('total')
                    ->label('Total Penjualan')
                    ->content(function (Get $get) {
                        return Formatter::currency(
                            (float) $get('total_revenue')
                        );
                    }),

                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}