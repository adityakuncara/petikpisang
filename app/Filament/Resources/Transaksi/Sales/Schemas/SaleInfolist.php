<?php

namespace App\Filament\Resources\Transaksi\Sales\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('product.name')
                    ->label('Produk :')
                    ->numeric(),
                TextEntry::make('sale_date')
                    ->label('Tanggal Penjualan :')
                    ->date('d M Y'),
                TextEntry::make('quantity')
                    ->label('Total Terjual :')
                    ->suffix(' Pcs')
                    ->numeric(),
                TextEntry::make('unit_price')
                    ->label('Harga Satuan :')
                    ->money('IDR', locale: 'id', decimalPlaces: 0),
                TextEntry::make('total_revenue')
                    ->label('Total Pendapatan :')
                    ->money('IDR', locale: 'id', decimalPlaces: 0),
                TextEntry::make('notes')
                    ->label('Catatan :')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('creator.name')
                    ->label('Dibuat oleh :')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Dibuat pada :')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Diubah pada :')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
            ]);
    }
}
