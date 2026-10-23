<?php

namespace App\Enums;

enum CourtType: string
{
    case Football = 'Football';
    case Tennis = 'Tennis';
    case Padel = 'Padel';
    case Swimming = 'Swimming';

    /** ['Football' => 'Football', ...] for selects and checkbox lists. */
    public static function options(): array
    {
        return array_column(self::cases(), 'value', 'value');
    }
}
