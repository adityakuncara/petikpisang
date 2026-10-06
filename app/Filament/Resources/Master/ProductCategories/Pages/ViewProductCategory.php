<?php

namespace App\Filament\Resources\Master\ProductCategories\Pages;

use App\Filament\Resources\Master\ProductCategories\ProductCategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductCategory extends ViewRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
