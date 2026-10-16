<?php

namespace App\Http\Controllers\Shop;

use App\Cart\CartManager;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartManager $carts) {}

    public function show(): View
    {
        return view('shop.cart', ['cart' => $this->carts->current()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $variant = ProductVariant::query()
            ->where('is_active', true)
            ->whereRelation('product', 'status', ProductStatus::Published)
            ->with('product', 'optionValues.option')
            ->findOrFail($data['variant']);

        ['item' => $item, 'limited' => $limited] = $this->carts->add($variant, (int) ($data['quantity'] ?? 1));

        $name = self::name($variant);

        if ($item === null) {
            return back()->with('shop.problem', "Sorry, the {$name} has just sold out.");
        }

        // The cart page marks the line that just arrived, and the count beside the cart in the header.
        return redirect()->route('cart.show')->with('shop.added', $item->getKey())->with('shop.notice', $limited
            ? "Only {$item->quantity} of the {$name} ".($item->quantity === 1 ? 'is' : 'are').' in stock, so that is how many are in your cart.'
            : "The {$name} is in your cart.");
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->own($item);

        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);

        $kept = $this->carts->setQuantity($item, (int) $data['quantity']);

        return redirect()->route('cart.show')->with('shop.notice', $kept < (int) $data['quantity']
            ? "Only {$kept} of the ".self::name($item->variant).' can be ordered at the moment.'
            : null);
    }

    public function destroy(CartItem $item): RedirectResponse
    {
        $this->own($item);

        $name = self::name($item->variant);
        $this->carts->remove($item);

        return redirect()->route('cart.show')->with('shop.notice', "The {$name} has been taken out of your cart.");
    }

    /** A shopper may only touch lines of the cart their own browser holds. */
    private function own(CartItem $item): void
    {
        abort_unless($item->cart_id === $this->carts->current()?->getKey(), 404);

        $item->setRelation('cart', $this->carts->current());
        $item->loadMissing('variant.product', 'variant.optionValues.option');
    }

    /** "Amani 3-seater sofa in Forest" */
    private static function name(ProductVariant $variant): string
    {
        $label = $variant->label();

        return $variant->product->name.($label === 'Standard' ? '' : ' in '.$label);
    }
}
