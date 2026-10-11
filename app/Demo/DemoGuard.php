<?php

namespace App\Demo;

use App\Models\Category;
use App\Models\Collection;
use App\Models\County;
use App\Models\DeliveryZone;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

use function Livewire\on;

/**
 * Keeps a demonstration account from changing what customers see.
 *
 * The check is made where the change is written, on the models themselves,
 * so it holds whichever screen, action or bulk action a change comes from.
 * Orders and stock are left alone: working those is what the account is for.
 */
class DemoGuard
{
    /** @var list<class-string<Model>> */
    private const PROTECTED = [
        Product::class, ProductVariant::class, ProductOption::class, ProductOptionValue::class, Media::class,
        Category::class, Collection::class, Page::class, Setting::class, DeliveryZone::class, County::class, User::class,
    ];

    public static function register(): void
    {
        foreach (self::PROTECTED as $model) {
            $model::saving(fn (Model $changed) => self::refuse($changed));
            $model::deleting(fn (Model $changed) => self::refuse($changed));
        }

        // In the back office the refusal is said where the person is, as a notice on the screen they are on.
        on('exception', function (mixed $component, Throwable $problem, callable $stopPropagation): void {
            if ($problem instanceof DemoAccountCannotChange) {
                Notification::make()
                    ->title('That change was not saved')
                    ->body($problem->getMessage())
                    ->warning()
                    ->persistent()
                    ->send();

                $stopPropagation();
            }
        });
    }

    private static function refuse(Model $changed): void
    {
        if (! Auth::guard('web')->user()?->is_demo) {
            return;
        }

        // Counting stock in or out changes the variant's stock and nothing else, and that is allowed.
        if ($changed instanceof ProductVariant && $changed->exists && array_diff(array_keys($changed->getDirty()), ['stock_on_hand', 'updated_at']) === []) {
            return;
        }

        // Signing in writes the remember-me token on the account itself.
        if ($changed instanceof User && $changed->exists && array_diff(array_keys($changed->getDirty()), ['remember_token', 'updated_at']) === []) {
            return;
        }

        throw new DemoAccountCannotChange;
    }
}
