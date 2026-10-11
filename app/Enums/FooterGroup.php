<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The lists of page links at the foot of every shop page.
 */
enum FooterGroup: string implements HasLabel
{
    case Help = 'help';
    case About = 'about';
    case Legal = 'legal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Help => 'Help',
            self::About => 'Sebule',
            self::Legal => 'The small print, on the bottom line',
        };
    }
}
