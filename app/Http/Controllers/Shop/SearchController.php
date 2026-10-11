<?php

namespace App\Http\Controllers\Shop;

use App\Catalogue\ProductListing;
use App\Catalogue\ProductSearch;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Search across the catalogue. What was typed stays in the address, so a
 * search can be shared or come back to.
 */
class SearchController extends Controller
{
    /** @var array<string, string> */
    public const SORTS = [
        'relevance' => 'Best match',
        'price-asc' => 'Price, low to high',
        'price-desc' => 'Price, high to low',
        'newest' => 'Newest',
    ];

    public function __invoke(Request $request): View
    {
        $term = ProductSearch::tidy($request->query('q'));
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : 'relevance';
        $searching = ProductSearch::words($term) !== [];

        $products = null;

        if ($searching) {
            $found = ProductSearch::apply(ProductListing::cards(), $term);

            $products = ($sort === 'relevance'
                ? $found->orderByDesc('relevance')->orderBy('products.id')
                : ProductListing::apply($found, ['sort' => $sort])
            )->paginate(12)->withQueryString();
        }

        return view('shop.search', [
            'term' => $term,
            'sort' => $sort,
            'searching' => $searching,
            'products' => $products,
            'categories' => $searching ? ProductSearch::categories($term) : collect(),
            // Somewhere to go when nothing was typed or nothing was found.
            'rooms' => Category::query()->whereNull('parent_id')->where('is_active', true)->orderBy('position')->get(),
        ]);
    }
}
