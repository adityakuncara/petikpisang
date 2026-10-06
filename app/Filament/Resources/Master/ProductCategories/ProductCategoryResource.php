<?php

namespace App\Filament\Resources\Master\ProductCategories;

use App\Filament\Resources\Master\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\Master\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\Master\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\Master\ProductCategories\Pages\ViewProductCategory;
use App\Filament\Resources\Master\ProductCategories\Schemas\ProductCategoryForm;
use App\Filament\Resources\Master\ProductCategories\Schemas\ProductCategoryInfolist;
use App\Filament\Resources\Master\ProductCategories\Tables\ProductCategoriesTable;
use App\Models\Master\ProductCategory;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Tag;

    protected static string|UnitEnum|null $navigationGroup = 'Master Produk';

    protected static ?string $recordTitleAttribute = 'Kategori Produk';

    protected static ?string $navigationLabel = 'Kategori Produk';

    protected static ?int $navigationSort = 1; 

    public static function form(Schema $schema): Schema
    {
        return ProductCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCategoriesTable::configure($table);
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
            'index' => ListProductCategories::route('/'),
            // 'create' => CreateProductCategory::route('/create'),
            // 'view' => ViewProductCategory::route('/{record}'),
            // 'edit' => EditProductCategory::route('/{record}/edit'),
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
