<?php

namespace App\Filament\Resources\Transaksi\Sales\Pages;

use App\Filament\Resources\Transaksi\Sales\SaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    public function getTitle(): string
    {
        return 'Buat Penjualan'; // 
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id(); // otomatis isi user login
        return $data;
    }
}
