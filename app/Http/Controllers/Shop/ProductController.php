<?php

namespace App\Http\Controllers\Shop;

use App\Catalogue\ProductListing;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __invoke(Request $request, Product $product): View
    {
        abort_unless($product->status === ProductStatus::Published, 404);

        $product->load([
            'category.parent',
            'collections',
            'media',
            'options.values.media',
            'variants' => fn ($query) => $query->where('is_active', true)->with('optionValues.option'),
        ]);

        abort_if($product->variants->isEmpty(), 404);

        // The chosen colour or finish is part of the address, so a link to it can be shared.
        $selected = $product->variants->first(fn (ProductVariant $variant) => self::key($variant) === $request->query('v'))
            ?? $product->variants->firstWhere('is_default', true)
            ?? $product->variants->first();

        $default = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();

        // Photos of the chosen colour come first. A colour with no photo of its own borrows the default's.
        $own = $selected->optionValues->flatMap(fn (ProductOptionValue $value) => $value->getMedia(ProductOptionValue::IMAGES));
        $borrowed = $own->isEmpty()
            ? $default->optionValues->flatMap(fn (ProductOptionValue $value) => $value->getMedia(ProductOptionValue::IMAGES))
            : collect();

        return view('shop.product', [
            'product' => $product,
            'selected' => $selected,
            'photos' => $own->concat($borrowed)->concat($product->getMedia(Product::GALLERY))->values(),
            'photosAreOfDefault' => $own->isEmpty() && $borrowed->isNotEmpty() && ! $selected->is($default),
            'defaultLabel' => $default->label(),
            'related' => ProductListing::cards()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->getKey())
                ->orderBy('products.id')
                ->limit(4)
                ->get(),
        ]);
    }

    /** The short name a variant goes by in the address, such as "forest" or "dark-walnut". */
    public static function key(ProductVariant $variant): string
    {
        return Str::slug($variant->label());
    }
}
