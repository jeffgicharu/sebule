<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MessageChannel: string implements HasLabel
{
    case Email = 'email';
    case Sms = 'sms';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms => 'Text message',
        };
    }
}
