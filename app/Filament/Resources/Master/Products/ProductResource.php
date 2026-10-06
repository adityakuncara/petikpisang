<?php

namespace App\Filament\Resources\Master\Products;

use App\Filament\Resources\Master\Products\Pages\CreateProduct;
use App\Filament\Resources\Master\Products\Pages\EditProduct;
use App\Filament\Resources\Master\Products\Pages\ListProducts;
use App\Filament\Resources\Master\Products\Pages\ViewProduct;
use App\Filament\Resources\Master\Products\Schemas\ProductForm;
use App\Filament\Resources\Master\Products\Schemas\ProductInfolist;
use App\Filament\Resources\Master\Products\Tables\ProductsTable;
use App\Models\Master\Product;
use App\Models\Master\ProductCategory;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Master Produk';

    protected static ?string $recordTitleAttribute = 'Produk';

    protected static ?string $navigationLabel = 'Produk';

    protected static ?int $navigationSort = 2; 

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
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
            'index' => ListProducts::route('/'),
            // 'create' => CreateProduct::route('/create'),
            // 'view' => ViewProduct::route('/{record}'),
            // 'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
