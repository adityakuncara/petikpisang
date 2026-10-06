<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitEnum:string
{
    case KG = 'kg';

    case GR = 'gr';

    case PCS = 'pcs';

    case BUNGKUS = 'bungkus';

    public function label(): string
    {
        return match($this){

            self::KG => 'Kilogram',

            self::GR => 'Gram',

            self::PCS => 'PCS',

            self::BUNGKUS => 'Bungkus',

        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $item)=>[
                $item->value=>$item->label()
            ])
            ->toArray();
    }
}