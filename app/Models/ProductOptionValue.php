<?php

namespace App\Models;

use App\Catalogue\ProductImage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One choice within an option, such as "Sand" or "3-seater". Photos that are
 * specific to the choice, such as the sofa in that colour, are held here.
 */
#[Fillable(['product_option_id', 'label', 'swatch', 'position'])]
class ProductOptionValue extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const IMAGES = 'images';

    /** @return BelongsTo<ProductOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    /** @return BelongsToMany<ProductVariant, $this> */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_option_value');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGES)->useDisk(ProductImage::DISK);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        ProductImage::registerConversions($this);
    }
}
