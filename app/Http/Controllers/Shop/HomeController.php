<?php

namespace App\Http\Controllers\Shop;

use App\Catalogue\ProductListing;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Each room is pictured by the first product in it that has a photo.
        $rooms = Category::query()->active()->topLevel()->orderBy('position')->with('children')->get()
            ->map(function (Category $room) {
                $room->setRelation('lead', ProductListing::cards()
                    ->whereIn('category_id', $room->children->modelKeys())
                    ->orderBy('products.id')
                    ->limit(4)
                    ->get()
                    ->first(fn ($product) => $product->mainPhoto() !== null));

                return $room;
            });

        $collections = Collection::query()->active()->orderBy('position')->limit(3)->get()
            ->each(fn (Collection $collection) => $collection->setRelation('showcase', ProductListing::cards()
                ->whereHas('collections', fn ($query) => $query->whereKey($collection->getKey()))
                ->orderBy('products.id')
                ->limit(4)
                ->get()));

        $offers = ProductListing::cards()
            ->whereHas('variants', fn ($query) => $query->whereNotNull('compare_at_price'))
            ->orderBy('products.id')
            ->limit(4)
            ->get();

        return view('shop.home', compact('rooms', 'collections', 'offers'));
    }
}
