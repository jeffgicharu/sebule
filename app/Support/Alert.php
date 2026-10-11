<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells whoever looks after the store that something has gone wrong: an
 * error on a page, a job that failed, a backup that did not run.
 *
 * Every alert is written to the log. It is also emailed when an address is
 * set, at most once in a quarter of an hour for the same trouble, so a fault
 * that repeats does not bury the inbox or use up the day's email.
 */
class Alert
{
    private const QUIET_MINUTES = 15;

    /** True while an alert is being raised, so trouble in raising one cannot raise another. */
    private static bool $raising = false;

    public static function raise(string $what, string $detail): void
    {
        if (self::$raising) {
            return;
        }

        self::$raising = true;

        try {
            Log::critical($what, ['detail' => $detail]);

            $address = config('store.alerts.email');

            if (blank($address) || ! Cache::add('alert.'.md5($what), true, now()->addMinutes(self::QUIET_MINUTES))) {
                return;
            }

            Mail::raw(
                $what."\n\n".$detail."\n\nSite: ".config('app.url')."\nRelease: ".(config('store.release') ?? 'unknown')."\nTime: ".now()->format('j F Y, H:i')
                    ."\n\nThe same trouble will not be reported again for ".self::QUIET_MINUTES.' minutes.',
                fn ($message) => $message->to($address)->subject('['.config('app.name').'] '.$what),
            );
        } catch (Throwable) {
            // An alert that cannot be sent must never become the next error.
        } finally {
            self::$raising = false;
        }
    }

    public static function forException(Throwable $problem): void
    {
        // A command or a background job has no page behind it.
        $request = request()->route() ? request()->method().' '.request()->path() : 'a command or a background job';

        self::raise(
            'Error: '.class_basename($problem),
            mb_substr($problem->getMessage(), 0, 500)."\n\nAt ".str_replace(base_path().'/', '', $problem->getFile()).':'.$problem->getLine()."\nDuring: ".$request,
        );
    }
}
