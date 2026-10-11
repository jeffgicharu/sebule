<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MessageStatus: string implements HasColor, HasLabel
{
    case Waiting = 'waiting';
    case Sent = 'sent';
    case Failed = 'failed';
    case NotSent = 'not_sent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting to go',
            self::Sent => 'Sent',
            self::Failed => 'Could not be sent',
            self::NotSent => 'Not sent',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Waiting => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::NotSent => 'gray',
        };
    }
}
