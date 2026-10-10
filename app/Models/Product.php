<?php

namespace App\Models;

use App\Catalogue\ProductImage;
use App\Enums\ProductStatus;
use App\Models\Concerns\HasSlug;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'category_id', 'name', 'slug', 'summary', 'description', 'materials', 'care',
    'assembly_required', 'warranty_months', 'status', 'seo_title', 'seo_description', 'published_at',
])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasSlug, InteractsWithMedia;

    /** Photos that show the product whatever variant is chosen, such as room settings. */
    public const GALLERY = 'gallery';

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'assembly_required' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->status === ProductStatus::Published && $product->published_at === null) {
                $product->published_at = now();
            }
        });
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Collection, $this> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class)->withPivot('position');
    }

    /** @return HasMany<ProductOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    /** @return HasOne<ProductVariant, $this> */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /** @param Builder<Product> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published);
    }

    /**
     * The photo that stands for the product in a list: the first photo of the
     * colour or finish shown first, or failing that the first general photo.
     */
    public function mainPhoto(): ?Media
    {
        $ofDefaultVariant = $this->defaultVariant?->optionValues
            ->map(fn (ProductOptionValue $value) => $value->getFirstMedia(ProductOptionValue::IMAGES))
            ->filter()
            ->first();

        return $ofDefaultVariant ?? $this->getFirstMedia(self::GALLERY);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::GALLERY)->useDisk(ProductImage::DISK);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        ProductImage::registerConversions($this);
    }
}
