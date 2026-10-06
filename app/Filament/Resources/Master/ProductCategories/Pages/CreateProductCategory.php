<?php

namespace App\Filament\Resources\Master\ProductCategories\Pages;

use App\Filament\Resources\Master\ProductCategories\ProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;
}
