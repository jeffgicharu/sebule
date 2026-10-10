<?php

namespace App\Catalogue;

use Spatie\MediaLibrary\HasMedia;

/**
 * The sizes every product photo is produced in, shared by the models that hold photos.
 */
class ProductImage
{
    /** Photos are public files, served straight from disk by the web server. */
    public const DISK = 'public';

    /** @var array<string, int> conversion name => width in pixels */
    public const WIDTHS = [
        'thumb' => 160,
        'small' => 480,
        'medium' => 960,
        'large' => 1600,
    ];

    public static function registerConversions(HasMedia $model): void
    {
        foreach (self::WIDTHS as $name => $width) {
            $model->addMediaConversion($name)
                ->width($width)
                ->format('webp')
                ->quality(80)
                ->queued();
        }
    }
}
