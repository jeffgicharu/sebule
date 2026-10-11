<?php

namespace App\Catalogue;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Keeps each product's searchable text: the words a customer might look for
 * it by. Search reads this one column, so it never has to join the catalogue
 * together while somebody waits.
 */
class SearchIndex
{
    private const WITH = ['category.parent', 'collections', 'options.values'];

    /**
     * The everyday words for the store's own colour and finish names, so that
     * "green chair" finds a chair that comes in Forest or Olive.
     *
     * @var array<string, string>
     */
    private const EVERYDAY = [
        'charcoal' => 'grey gray',
        'sand' => 'beige',
        'oat' => 'beige cream',
        'ivory' => 'cream white',
        'natural' => 'beige',
        'clay' => 'terracotta orange',
        'olive' => 'green',
        'forest' => 'green',
        'ocean' => 'blue',
        'tan' => 'brown',
        'chocolate' => 'brown',
        'mustard' => 'yellow',
        'ebony' => 'black',
        'dark walnut' => 'brown',
        'mahogany' => 'brown',
    ];

    public static function textFor(Product $product): string
    {
        $product->loadMissing(self::WITH);

        $colours = $product->options->flatMap->values->pluck('label')->unique();

        return (string) Str::of(implode(' ', array_filter([
            $product->name,
            $product->category?->name,
            $product->category?->parent?->name,
            $product->collections->pluck('name')->join(' '),
            $colours->join(' '),
            $colours->map(fn (string $label) => self::EVERYDAY[Str::lower($label)] ?? null)->filter()->unique()->join(' '),
            $product->materials,
            $product->summary,
            strip_tags((string) $product->description),
        ])))->lower()->squish();
    }

    /** Write a product's searchable text, without touching its timestamps or firing its events again. */
    public static function refresh(Product $product): void
    {
        Product::query()->whereKey($product->getKey())->toBase()->update(['search_text' => self::textFor($product)]);
    }

    /** Write every product's searchable text. Returns how many were written. */
    public static function rebuild(): int
    {
        $written = 0;

        Product::query()->with(self::WITH)->chunkById(200, function ($products) use (&$written): void {
            foreach ($products as $product) {
                self::refresh($product);
                $written++;
            }
        });

        return $written;
    }
}
