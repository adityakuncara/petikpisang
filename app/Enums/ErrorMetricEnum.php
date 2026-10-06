<?php

declare(strict_types=1);

namespace App\Enums;

enum ErrorMetricEnum:string
{
    case MAE='mae';

    case MAPE='mape';

    case RMSE='rmse';

    case SSE='sse';

    public function label(): string
    {
        return strtoupper($this->value);
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