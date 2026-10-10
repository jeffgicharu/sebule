<?php

namespace App\Providers;

use App\Cart\CartManager;
use App\Models\Category;
use App\Models\Collection;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
            ]));
        });

        // The cart belongs to the visitor, so it is never part of what is cached above.
        View::composer('components.shop.layout', fn ($view) => $view->with('cartPieces', app(CartManager::class)->pieces()));

        $this->limitRequests();

        $forgetMenu = fn () => Cache::forget(self::NAVIGATION);

        Category::saved($forgetMenu);
        Category::deleted($forgetMenu);
        Collection::saved($forgetMenu);
        Collection::deleted($forgetMenu);
    }

    /**
     * How often one visitor may do the things that cost something. Each limit
     * counts on its own, so a busy cart never uses up the allowance for paying.
     */
    private function limitRequests(): void
    {
        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(90)->by('cart|'.$request->ip()));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(30)->by('checkout|'.$request->ip()));
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
