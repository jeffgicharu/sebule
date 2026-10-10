<?php

namespace App\Filament\Support;

use App\Catalogue\ProductImage;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

/**
 * An upload field for product photos. They go to the public disk, which the
 * web server hands out directly, rather than the private default.
 */
class PhotoUpload
{
    public static function make(string $name, string $collection): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($name)
            ->collection($collection)
            ->disk(ProductImage::DISK)
            ->visibility('public')
            ->image()
            ->multiple()
            ->reorderable()
            ->maxSize(10240);
    }
}
