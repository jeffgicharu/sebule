<?php

namespace App\Seo;

use App\Catalogue\ProductImage;
use App\Enums\Availability;
use App\Http\Controllers\Shop\ProductController;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What each page tells search engines about itself, in schema.org terms. Every method
 * returns one or more nodes; the layout prints them together as one JSON-LD graph.
 */
class StructuredData
{
    public const CURRENCY = 'KES';

    /**
     * The store and its website. Given on the home page.
     *
     * @return list<array<string, mixed>>
     */
    public static function store(string $description): array
    {
        $home = route('home');
        $showroom = config('store.showroom');

        return [
            [
                '@type' => 'FurnitureStore',
                '@id' => $home.'#store',
                'name' => config('app.name'),
                'url' => $home,
                'logo' => asset('icon-512.png'),
                'image' => asset('images/site/share-1200.jpg'),
                'description' => $description,
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $showroom['street'],
                    'addressLocality' => $showroom['city'],
                    'addressCountry' => $showroom['country'],
                ],
                'openingHoursSpecification' => [[
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $showroom['days'],
                    'opens' => $showroom['opens'],
                    'closes' => $showroom['closes'],
                ]],
                'areaServed' => ['@type' => 'Country', 'name' => 'Kenya'],
                'currenciesAccepted' => self::CURRENCY,
                'paymentAccepted' => 'M-Pesa, Credit Card, Debit Card',
                'priceRange' => 'KSh',
            ],
            [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'url' => $home,
                'name' => config('app.name'),
                'inLanguage' => 'en-KE',
                'publisher' => ['@id' => $home.'#store'],
            ],
        ];
    }

    /**
     * The path from the home page to this page.
     *
     * @param  array<int, array{0: string, 1: string|null}>  $trail  [label, url]; the last is this page
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $trail, string $current): array
    {
        $steps = [[config('app.name'), route('home')], ...$trail];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($steps)->values()->map(fn (array $step, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $step[0],
                'item' => $step[1] ?? $current,
            ])->all(),
        ];
    }

    /**
     * A page that lists products: a category or a collection.
     *
     * @param  iterable<Product>  $products
     * @return array<string, mixed>
     */
    public static function listing(string $name, string $url, ?string $description, iterable $products): array
    {
        $items = collect($products)->values();

        return array_filter([
            '@type' => 'CollectionPage',
            '@id' => $url.'#page',
            'url' => $url,
            'name' => $name,
            'description' => $description,
            'isPartOf' => ['@id' => route('home').'#website'],
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $items->count(),
                'itemListElement' => $items->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $product->name,
                    'url' => route('shop.product', $product),
                ])->all(),
            ],
        ]);
    }

    /**
     * A product with one offer for each colour or finish it is sold in.
     *
     * @param  Collection<int, Media>  $photos
     * @return array<string, mixed>
     */
    public static function product(Product $product, Collection $photos): array
    {
        $url = route('shop.product', $product);
        $category = $product->category;

        $offers = $product->variants->map(fn (ProductVariant $variant) => [
            '@type' => 'Offer',
            'url' => $product->variants->count() > 1 ? $url.'?v='.ProductController::key($variant) : $url,
            'sku' => $variant->sku,
            'name' => trim($product->name.' '.$variant->label()),
            'price' => self::amount($variant->price),
            'priceCurrency' => self::CURRENCY,
            'availability' => 'https://schema.org/'.match ($variant->availability()) {
                Availability::InStock => 'InStock',
                Availability::LowStock => 'LimitedAvailability',
                Availability::MadeToOrder => 'MadeToOrder',
                Availability::OutOfStock => 'OutOfStock',
            },
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@id' => route('home').'#store'],
        ])->values();

        return array_filter([
            '@type' => 'Product',
            '@id' => $url.'#product',
            'url' => $url,
            'name' => $product->name,
            'description' => $product->summary ?: Str::limit(trim(strip_tags((string) $product->description)), 300),
            'image' => $photos->take(6)->map(fn (Media $photo) => self::photoUrl($photo))->values()->all(),
            'sku' => $product->variants->firstWhere('is_default', true)?->sku ?? $product->variants->first()?->sku,
            'brand' => ['@type' => 'Brand', 'name' => config('app.name')],
            'category' => $category ? implode(' > ', array_filter([$category->parent?->name, $category->name])) : null,
            'material' => $product->materials,
            'offers' => $offers->count() === 1 ? $offers->first() : [
                '@type' => 'AggregateOffer',
                'priceCurrency' => self::CURRENCY,
                'lowPrice' => self::amount((int) $product->variants->min('price')),
                'highPrice' => self::amount((int) $product->variants->max('price')),
                'offerCount' => $offers->count(),
                'offers' => $offers->all(),
            ],
        ]);
    }

    /** A photo in the size meant for sharing: a JPEG every service can read. */
    public static function photoUrl(Media $photo): string
    {
        return $photo->hasGeneratedConversion(ProductImage::SHARE)
            ? $photo->getFullUrl(ProductImage::SHARE)
            : $photo->getFullUrl('large');
    }

    /** Cents as the plain decimal schema.org expects, such as "98000.00". */
    public static function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
