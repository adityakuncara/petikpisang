<?php

namespace App\Filament\Resources\Master\Products\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Tables\Actions\HeaderActionsPosition;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

// Ambil Action khusus Table menggunakan ALIAS agar tidak bentrok dengan komponen Filament utama Anda
use Filament\Actions\Action as TableAction;
use Filament\Forms\Components\TextInput;
use App\Services\Inventory\StockService;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->formatStateUsing(fn (string $state): string => Str::upper($state))
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->limit(50)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->searchable()
                    ->icon('heroicon-m-sparkles')
                    ->color('warning'),
                TextColumn::make('unit')
                    ->label('Satuan')                
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state?->value ?? $state) {
                        'kg' => 'Kilogram',
                        'gr' => 'Gram',
                        'pcs' => 'Pcs',
                        'bungkus' => 'Bungkus',
                        default => $state,
                    })
                    ->icon('heroicon-m-tag')
                    ->color('info'),
                TextColumn::make('selling_price')
                    ->label('HPP')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Ketersediaan')
                    ->formatStateUsing(fn ($state) => $state == 1 ? 'Tersedia' : 'Tidak Tersedia')
                    ->badge()
                    ->color(fn ($state) => $state == 1 ? 'success' : 'danger'),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')                
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diubah Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Dihapus Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([

            ])
            ->actions([
                // Gunakan TableAction agar tombol ini muncul mandiri di setiap baris (sejajar Edit/View)
                TableAction::make('tambah_stok')
                    ->label('Stok')
                    ->icon('heroicon-m-plus-circle')
                    ->color('success')
                    ->button() // Opsional: Mengubah tampilan menjadi button agar terlihat tegas, hapus baris ini jika ingin berupa link biasa
                    ->form([
                        TextInput::make('product_name')
                            ->label('Nama Produk')
                            ->disabled()
                            ->default(fn ($record) => $record->name),
                        
                        TextInput::make('current_stock')
                            ->label('Stok Saat Ini')
                            ->disabled()
                            ->default(fn ($record) => $record->stock),

                        TextInput::make('quantity_to_add')
                            ->label('Jumlah Stok yang Ditambah')
                            ->numeric()
                            ->rules(['numeric', 'min:0.001'])
                            ->required(),
                    ])
                    ->action(function (array $data, $record) {
                        // Secara otomatis mengambil $record->id dari baris yang diklik
                        app(StockService::class)->increase(
                            productId: $record->id,
                            quantity: (float) $data['quantity_to_add']
                        );

                        Notification::make()
                            ->success()
                            ->title('Stok Berhasil Ditambah')
                            ->body("Stok untuk produk \"{$record->name}\" telah berhasil ditambahkan.")
                            ->send();
                    }),

                ActionGroup::make([
                    ViewAction::make()
                    ->color('info')
                    ->button(),
                    EditAction::make()
                    ->button(),
                    DeleteAction::make()
                    ->button(),
                ])
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Kategori Produk')
                    ->body('Kategori Produk berhasil ditambahkan.'),
                    )
                    ->slideOver(), 
            ]);
    }
}