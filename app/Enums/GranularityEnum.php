<?php

declare(strict_types=1);

namespace App\Enums;

enum GranularityEnum:string
{
    case WEEKLY='weekly';

    case MONTHLY='monthly';

    public function label(): string
    {
        return match($this){

            self::WEEKLY=>'Mingguan',

            self::MONTHLY=>'Bulanan',

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