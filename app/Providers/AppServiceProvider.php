<?php

namespace App\Providers;

use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
    }
}
