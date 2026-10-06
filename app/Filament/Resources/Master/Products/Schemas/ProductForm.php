<?php

namespace App\Filament\Resources\Master\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use App\Models\Master\Product;
use App\Models\Master\ProductCategory;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. Mengubah menjadi Select dari Model Kategori
                Select::make('product_category_id')
                    ->label('Kategori Barang')
                    ->relationship('category', 'name') // Mengambil relasi 'category' dan menampilkan kolom 'name'
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live() // Memaksa form me-refresh state ketika kategori dipilih
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                        // Jika kategori dikosongkan, kosongkan juga kolom kode produknya
                        if (! $state) {
                            $set('code', null);
                            return;
                        }

                        // Ambil data nama kategori
                        $category = ProductCategory::find($state);
                        if (! $category) return;

                        // Bersihkan nama kategori (ambil kata pertama, jadikan lowercase/slug tanpa spasi)
                        $categorySlug = strtolower(str_replace(' ', '', $category->name));

                        // Hitung jumlah produk yang sudah ada di kategori ini untuk sistem increment
                        $count = Product::where('product_category_id', $state)->count() + 1;

                        // Pad angka increment menjadi 3 digit (contoh: 001, 002, dst)
                        $incrementNumber = str_pad((string) $count, 3, '0', STR_PAD_LEFT);

                        // Gabungkan menjadi kode baru (contoh: kripik001)
                        $generatedCode = $categorySlug . $incrementNumber;

                        // Set nilai field 'code' secara otomatis
                        $set('code', $generatedCode);
                    }),

                // 2. Kolom Kode Barang (dibuat read-only/disabled agar user tidak merusak auto-generate)
                TextInput::make('code')
                    ->label('Kode Barang')
                    ->required()
                    ->maxLength(255)
                    ->unique(Product::class, 'code', ignoreRecord: true) // Mencegah kode ganda
                    ->dehydrated() // Tetap mengirimkan data ke database meskipun dalam kondisi disabled/read-only
                    ->readOnly(), // Mengunci field agar user tidak mengubah manual

                TextInput::make('name')
                    ->label('Nama Barang')
                    ->required(),

                Select::make('unit')
                    ->label('Satuan Barang')   
                    ->options([
                        'kg' => 'Kilogram', 
                        'gr' => 'Gram', 
                        'pcs' => 'Pcs', 
                        'bungkus' => 'Bungkus'
                    ])
                    ->required(),

                TextInput::make('selling_price')
                    ->label('Harga Jual (HPP)')
                    ->required()
                    ->numeric()
                    ->default(0.0)
                    ->prefix('Rp.'),

                TextInput::make('stock')
                    ->label('Stok Barang')
                    ->numeric(),

                Textarea::make('description')
                    ->label('Deskripsi Barang')   
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Status Barang')   
                    ->required(),
            ]);
    }
}
