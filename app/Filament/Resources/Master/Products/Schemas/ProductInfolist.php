<?php

namespace App\Filament\Resources\Master\Products\Schemas;

use App\Models\Master\Product;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('category.name')
                    ->label('Kategori Produk')
                    ->numeric()
                    ->badge()
                    ->icon('heroicon-m-sparkles')
                    ->color('warning'),
                TextEntry::make('code')
                    ->label('Kode Produk')
                    ->formatStateUsing(fn (string $state): string => Str::upper($state)),
                TextEntry::make('name')
                    ->label('Nama Produk'),
                TextEntry::make('unit')
                    ->label('Satuan/Unit')
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
                TextEntry::make('selling_price')
                    ->label('Harga Jual')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->weight(FontWeight::Bold)
                    ->color('success')
                    ->icon('heroicon-m-banknotes'),
                TextEntry::make('stock')
                    ->label('Stok Saat Ini')
                    ->numeric()
                    ->placeholder('-')
                    ->weight(FontWeight::Bold)
                    ->color('danger')
                    ->icon('heroicon-m-inbox'),
                TextEntry::make('description')
                    ->label('Deskripsi')
                    ->placeholder('-'),
                IconEntry::make('is_active')
                    ->label('Status Produk')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label('Dibuat pada')
                    ->dateTime('d M Y')
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Diubah pada')
                    ->dateTime('d M Y')
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->label('Dihapus pada')
                    ->dateTime('d M Y')
                    ->placeholder('-')
                    ->visible(fn (Product $record): bool => $record->trashed()),
            ]);
    }
}
