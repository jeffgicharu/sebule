<?php

namespace App\Http\Controllers\Shop;

use App\Catalogue\ProductListing;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __invoke(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        $category->load(['parent', 'children' => fn ($query) => $query->where('is_active', true)]);

        // A room lists everything in its sub-categories.
        $categoryIds = $category->children->modelKeys() ?: [$category->getKey()];

        $filters = $request->only(['sort', 'price', 'colour', 'material', 'width', 'availability']);
        $inCategory = ProductListing::cards()->whereIn('category_id', $categoryIds);

        return view('shop.category', [
            'category' => $category,
            'siblings' => $category->parent?->children()->where('is_active', true)->get() ?? $category->children,
            'colours' => ProductListing::colours($inCategory),
            'materials' => ProductListing::materials($inCategory),
            'filters' => $filters,
            'products' => ProductListing::apply($inCategory->clone(), $filters)->paginate(12)->withQueryString(),
        ]);
    }
}
