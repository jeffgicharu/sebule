<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Collection;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Cache key for the shop's menu: the rooms, their sub-categories and the collections. */
    public const NAVIGATION = 'shop.navigation';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // On a phone a table row becomes a card, so staff never scroll sideways to reach a column or a button.
        Table::configureUsing(fn (Table $table) => $table->stackedOnMobile());

        // The menu is on every shop page and changes rarely, so it is read once and kept until staff edit it.
        View::composer('components.shop.layout', function ($view): void {
            $view->with(Cache::remember(self::NAVIGATION, now()->addHour(), fn () => [
                'menuRooms' => Category::query()->active()->topLevel()->orderBy('position')
                    ->with(['children' => fn ($query) => $query->where('is_active', true)])
                    ->get(),
                'menuCollections' => Collection::query()->active()->orderBy('position')->get(),
            ]));
        });

        $forgetMenu = fn () => Cache::forget(self::NAVIGATION);

        Category::saved($forgetMenu);
        Category::deleted($forgetMenu);
        Collection::saved($forgetMenu);
        Collection::deleted($forgetMenu);
    }
}
