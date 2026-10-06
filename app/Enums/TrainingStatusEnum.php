<?php

declare(strict_types=1);

namespace App\Enums;

enum TrainingStatusEnum:string
{
    case DRAFT='draft';

    case PROCESSING='processing';

    case COMPLETED='completed';

    case FAILED='failed';

    public function label(): string
    {
        return ucfirst($this->value);
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