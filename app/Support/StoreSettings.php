<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The settings staff can change without a developer. Each one stands in for
 * a value in config/store.php: the code reads config('store.…') as it always
 * has, and finds what staff saved in place of what the file ships with.
 */
class StoreSettings
{
    private const CACHE = 'store.settings';

    /**
     * The settings that can be changed, by the configuration key each replaces.
     *
     * @var list<string>
     */
    public const KEYS = [
        'store.phone', 'store.whatsapp', 'store.email.reply_to',
        'store.showroom.street', 'store.showroom.city', 'store.showroom.days', 'store.showroom.opens', 'store.showroom.closes',
        'store.vat_rate', 'store.hold_minutes',
        'store.home.hero.eyebrow', 'store.home.hero.heading', 'store.home.hero.text',
        'store.home.banner.eyebrow', 'store.home.banner.heading', 'store.home.banner.link',
        'store.home.promises',
    ];

    /** Put what staff have saved over the shipped configuration. Called once as the application starts. */
    public static function apply(): void
    {
        try {
            $saved = Cache::rememberForever(self::CACHE, fn (): array => Setting::query()->whereIn('key', self::KEYS)->pluck('value', 'key')->all());
        } catch (Throwable) {
            // The table does not exist until the first migration has run. The shipped values stand.
            return;
        }

        foreach ($saved as $key => $value) {
            config([$key => $value]);
        }
    }

    /**
     * Save some settings and bring them into force at once.
     *
     * @param  array<string, mixed>  $values  by configuration key
     */
    public static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::KEYS, true)) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        Cache::forget(self::CACHE);
        self::apply();
    }
}
