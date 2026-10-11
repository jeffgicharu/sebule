<?php

namespace App\Providers;

use App\Cart\CartManager;
use App\Catalogue\SearchIndex;
use App\Demo\DemoGuard;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Support\StoreSettings;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /** Cache key for the shop's menu: the rooms, their sub-categories and the collections. */
    public const NAVIGATION = 'shop.menu';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request, so a page asks for the cart once however many parts of it need it.
        $this->app->scoped(CartManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // On a phone a table row becomes a card, so staff never scroll sideways to reach a column or a button.
        Table::configureUsing(fn (Table $table) => $table->stackedOnMobile());

        // The menu is on every shop page and changes rarely, so it is read once and kept until staff edit it.
        // It is cached as plain arrays: the cache does not restore objects.
        View::composer('components.shop.layout', function ($view): void {
            $view->with(Cache::remember(self::NAVIGATION, now()->addHour(), fn () => [
                'menuRooms' => Category::query()->active()->topLevel()->orderBy('position')
                    ->with(['children' => fn ($query) => $query->where('is_active', true)])
                    ->get()
                    ->map(fn (Category $room) => [
                        'name' => $room->name,
                        'slug' => $room->slug,
                        'children' => $room->children->map->only(['name', 'slug'])->all(),
                    ])
                    ->all(),
                'menuCollections' => Collection::query()->active()->orderBy('position')->get()->map->only(['name', 'slug'])->all(),
                // The store's own pages, by the footer list each belongs in.
                'footerPages' => Page::query()->published()->whereNotNull('footer_group')->orderBy('position')->get()
                    ->groupBy(fn (Page $page) => $page->footer_group->value)
                    ->map(fn ($pages) => $pages->map->only(['title', 'slug'])->values()->all())
                    ->all(),
            ]));
        });

        // The cart and the account belong to the visitor, so they are never part of what is cached above.
        View::composer('components.shop.layout', fn ($view) => $view->with([
            'cartPieces' => app(CartManager::class)->pieces(),
            'shopper' => Auth::guard('customer')->user(),
        ]));

        $this->limitRequests();

        StoreSettings::apply();
        DemoGuard::register();

        // A colour renamed or a category renamed changes what its products are found by.
        ProductOptionValue::saved(fn (ProductOptionValue $value) => ($product = $value->option?->product) ? SearchIndex::refresh($product) : null);
        ProductOptionValue::deleted(fn (ProductOptionValue $value) => ($product = $value->option?->product) ? SearchIndex::refresh($product) : null);
        Category::saved(function (Category $category): void {
            if ($category->wasChanged('name')) {
                Product::query()->whereIn('category_id', [$category->id, ...$category->children()->pluck('id')])->get()->each(SearchIndex::refresh(...));
            }
        });

        $forgetMenu = fn () => Cache::forget(self::NAVIGATION);

        Category::saved($forgetMenu);
        Category::deleted($forgetMenu);
        Collection::saved($forgetMenu);
        Collection::deleted($forgetMenu);
        Page::saved($forgetMenu);
        Page::deleted($forgetMenu);
    }

    /**
     * How often one visitor may do the things that cost something. Each limit
     * counts on its own, so a busy cart never uses up the allowance for paying.
     */
    private function limitRequests(): void
    {
        RateLimiter::for('demo', fn (Request $request) => Limit::perMinute(12)->by('demo|'.$request->ip()));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(40)->by('search|'.$request->ip()));
        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(90)->by('cart|'.$request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(30)->by('checkout|'.$request->ip()));
        // Placing an order holds stock and writes to the customer, so one visitor may place only so many.
        RateLimiter::for('orders', fn (Request $request) => [
            Limit::perMinutes(10, 6)->by('orders|'.$request->ip()),
            Limit::perDay(25)->by('orders-day|'.$request->ip()),
        ]);

        // Signing in, opening accounts and asking for password links are rationed against guessing and flooding.
        RateLimiter::for('account-login', fn (Request $request) => [
            Limit::perMinute(5)->by('login|'.Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('login|'.$request->ip()),
        ]);
        RateLimiter::for('account-register', fn (Request $request) => Limit::perHour(10)->by('register|'.$request->ip()));
        // A link by email costs an email, so one address is sent only a few an hour. Choosing the new password is rationed separately.
        RateLimiter::for('account-reset', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('reset|'.$request->ip()),
            Limit::perHour(3)->by('reset|'.Str::lower((string) $request->input('email'))),
        ]);
        RateLimiter::for('account-new-password', fn (Request $request) => Limit::perMinutes(10, 10)->by('new-password|'.$request->ip()));

        RateLimiter::for('payment-status', fn (Request $request) => Limit::perMinute(120)->by('payment-status|'.$request->ip()));
        RateLimiter::for('payment-confirmations', fn (Request $request) => Limit::perMinute(600)->by('payment-confirmations|'.$request->ip()));

        // A prompt lands on somebody's phone, so these are rationed for each order and for each visitor.
        RateLimiter::for('payment-prompts', fn (Request $request) => [
            // The order is still its token here: the limit is counted before the order is looked up.
            Limit::perMinute(6)->by('prompts|order|'.(is_string($order = $request->route('order')) ? $order : $order?->token)),
            Limit::perMinute(30)->by('prompts|visitor|'.$request->ip()),
        ]);
    }
}
