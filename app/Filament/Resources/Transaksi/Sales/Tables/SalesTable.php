<?php

namespace App\Filament\Resources\Transaksi\Sales\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\CreateAction;
use Filament\Tables\Actions\HeaderActionsPosition;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sale_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Produk')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->searchable()
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('total_revenue')
                    ->label('Pendapatan')
                    ->searchable()
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('created_by')
                    ->label('Dibuat oleh')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-m-user')
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diubah pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sale_date', 'desc')
            ->filters([
                
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make()
                        ->modalHeading('Lihat Penjualan')
                        ->modalButton('Tutup'),
                    EditAction::make()
                        ->modalHeading('Ubah Penjualan')
                        ->modalButton('Ubah & Simpan'),
                ])
            ])
            ->toolbarActions([
                    CreateAction::make()
                        ->label('Tambah Transaksi')
                        ->modalHeading('Tambah Penjualan')
                        ->modalButton('Simpan Penjualan')
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
