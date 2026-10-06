<?php

namespace App\Filament\Resources\Transaksi\Sales;

use App\Filament\Resources\Transaksi\Sales\Pages\CreateSale;
use App\Filament\Resources\Transaksi\Sales\Pages\EditSale;
use App\Filament\Resources\Transaksi\Sales\Pages\ListSales;
use App\Filament\Resources\Transaksi\Sales\Pages\ViewSale;
use App\Filament\Resources\Transaksi\Sales\Schemas\SaleForm;
use App\Filament\Resources\Transaksi\Sales\Schemas\SaleInfolist;
use App\Filament\Resources\Transaksi\Sales\Tables\SalesTable;
use App\Models\Transaksi\Sale;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi';
    
    protected static ?string $ModelLabel = 'Penjualan';

    protected static ?string $pluralModelLabel = 'Penjualan';

    protected static ?string $recordTitleAttribute = 'Penjualan';

    protected static ?string $navigationLabel = 'Penjualan';

    protected static ?string $breadcrumb = 'Transaksi';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return SaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            // 'create' => CreateSale::route('/create'),
            // 'view' => ViewSale::route('/{record}'),
            // 'edit' => EditSale::route('/{record}/edit'),
        ];
    }

}
