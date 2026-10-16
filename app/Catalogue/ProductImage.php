<?php

namespace App\Catalogue;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

    /** The conversion shown when a page is shared or listed by a search engine. */
    public const SHARE = 'share';

    /** Every width a photo has been produced at, as an image's srcset. */
    public static function srcset(Media $media): string
    {
        return collect(self::WIDTHS)->except('thumb')
            ->filter(fn (int $width, string $name) => $media->hasGeneratedConversion($name))
            ->map(fn (int $width, string $name) => $media->getUrl($name).' '.$width.'w')
            ->implode(', ');
    }

    public static function registerConversions(HasMedia $model): void
    {
        foreach (self::WIDTHS as $name => $width) {
            $model->addMediaConversion($name)
                ->width($width)
                ->format('webp')
                ->quality(80)
                ->queued();
        }

        // A JPEG for link previews and search results, which do not all read WebP.
        $model->addMediaConversion(self::SHARE)
            ->width(1200)
            ->format('jpg')
            ->quality(82)
            ->queued();
    }
}
