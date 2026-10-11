<?php

namespace App\Catalogue;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds products for what a customer typed. Every word has to match a
 * product, either as written or closely enough to be a slip of the fingers,
 * and products whose own name matches come first.
 */
class ProductSearch
{
    /** The longest search kept, and the most words of it that are used. */
    public const MAX_LENGTH = 80;

    private const MAX_WORDS = 6;

    /**
     * How alike a typed word and a word of the product must be to count as
     * the same word, from 0 to 1. A short word has to be nearer the mark,
     * because one changed letter makes it a different word: "lamu" is not "lamp".
     */
    private static function closeEnough(string $word): ?float
    {
        return match (true) {
            mb_strlen($word) <= 3 => null,
            mb_strlen($word) === 4 => 0.7,
            default => 0.5,
        };
    }

    /** What was typed, tidied: one space between words and no more than is kept. */
    public static function tidy(?string $typed): string
    {
        return (string) Str::of((string) $typed)->squish()->limit(self::MAX_LENGTH, '');
    }

    /**
     * @return list<string>
     */
    public static function words(string $term): array
    {
        return collect(preg_split('/[\s,]+/u', Str::lower($term)))
            ->map(fn (string $word) => trim($word, " \t.;:!?\"'()"))
            ->filter(fn (string $word) => mb_strlen($word) >= 2)
            ->unique()
            ->take(self::MAX_WORDS)
            ->values()
            ->all();
    }

    /**
     * Narrow a product list to what matches, and give each product a
     * "relevance" to sort by.
     *
     * @param  Builder<Product>  $products
     * @return Builder<Product>
     */
    public static function apply(Builder $products, string $term): Builder
    {
        $words = self::words($term);

        if ($words === []) {
            return $products->whereRaw('false');
        }

        $score = [];
        $bindings = [];

        foreach ($words as $word) {
            $anywhere = '%'.addcslashes($word, '%_\\').'%';

            $products->where(fn (Builder $either) => $either
                ->where('products.search_text', 'like', $anywhere)
                ->when(self::closeEnough($word), fn (Builder $close, float $enough) => $close
                    ->orWhereRaw('word_similarity(?, products.search_text) >= ?', [$word, $enough])));

            // A word in the product's own name counts for most, then how close the name is, then the rest of what is known about it.
            $score[] = '(case when lower(products.name) like ? then 3 else 0 end) + word_similarity(?, lower(products.name)) * 2 + word_similarity(?, products.search_text)';
            array_push($bindings, $anywhere, $word, $word);
        }

        return $products->selectRaw('('.implode(' + ', $score).') as relevance', $bindings);
    }

    /**
     * Categories whose name is what was typed, or close to it.
     *
     * @return Collection<int, Category>
     */
    public static function categories(string $term): Collection
    {
        $words = self::words($term);

        if ($words === []) {
            return new Collection;
        }

        // Every word has to be in the category's name or its room's, so "dining table" offers Dining tables and not every table.
        $named = "lower(categories.name || ' ' || coalesce((select rooms.name from categories as rooms where rooms.id = categories.parent_id), ''))";

        return Category::query()
            ->where('is_active', true)
            ->where(function (Builder $all) use ($words, $named): void {
                foreach ($words as $word) {
                    $all->where(fn (Builder $either) => self::wordIn($either, $word, $named));
                }

                // And at least one of them in its own name, or "bed" would offer every category in the bedroom.
                $all->where(function (Builder $own) use ($words): void {
                    foreach ($words as $word) {
                        $own->orWhere(fn (Builder $either) => self::wordIn($either, $word, 'lower(categories.name)'));
                    }
                });
            })
            ->with('parent')
            ->orderByRaw('parent_id is not null')
            ->orderBy('position')
            ->limit(6)
            ->get();
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    private static function wordIn(Builder $query, string $word, string $text): Builder
    {
        return $query
            ->whereRaw("{$text} like ?", ['%'.addcslashes($word, '%_\\').'%'])
            ->when(self::closeEnough($word), fn (Builder $close, float $enough) => $close
                ->orWhereRaw("word_similarity(?, {$text}) >= ?", [$word, $enough]));
    }
}
