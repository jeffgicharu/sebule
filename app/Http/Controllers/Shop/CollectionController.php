<?php

namespace App\Http\Controllers\Shop;

use App\Catalogue\ProductListing;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Contracts\View\View;

class CollectionController extends Controller
{
    public function __invoke(Collection $collection): View
    {
        abort_unless($collection->is_active, 404);

        return view('shop.collection', [
            'collection' => $collection,
            'products' => ProductListing::cards()
                ->join('collection_product', 'collection_product.product_id', '=', 'products.id')
                ->where('collection_product.collection_id', $collection->getKey())
                ->orderBy('collection_product.position')
                ->get(),
        ]);
    }
}
