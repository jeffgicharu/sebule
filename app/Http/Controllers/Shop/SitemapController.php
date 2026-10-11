<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Every page a search engine should know about: the home page, the rooms and their
     * sub-categories, the collections, the products on sale and the store's own pages.
     */
    public function __invoke(): Response
    {
        $pages = collect([['url' => route('home'), 'changed' => null]])
            ->concat(Category::query()->active()->orderBy('id')->get(['slug', 'updated_at'])
                ->map(fn (Category $category) => ['url' => route('shop.category', $category), 'changed' => $category->updated_at]))
            ->concat(Collection::query()->active()->orderBy('id')->get(['slug', 'updated_at'])
                ->map(fn (Collection $collection) => ['url' => route('shop.collection', $collection), 'changed' => $collection->updated_at]))
            ->concat(Product::query()->published()->whereHas('variants', fn ($query) => $query->where('is_active', true))->orderBy('id')->get(['slug', 'updated_at'])
                ->map(fn (Product $product) => ['url' => route('shop.product', $product), 'changed' => $product->updated_at]))
            ->concat(Page::query()->published()->orderBy('position')->get(['slug', 'updated_at'])
                ->map(fn (Page $page) => ['url' => route('shop.page', $page), 'changed' => $page->updated_at]));

        return response()
            ->view('shop.sitemap', ['pages' => $pages])
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
