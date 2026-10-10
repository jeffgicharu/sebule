<?php

namespace App\Support;

class Release
{
    /**
     * The commit this build was made from, or null when the code is not a built release.
     *
     * The deploy pipeline writes it to a REVISION file in the release. This is read by the
     * configuration files; everywhere else, use config('store.release').
     */
    public static function id(): ?string
    {
        $file = dirname(__DIR__, 2).'/REVISION';

        return env('APP_RELEASE') ?: (is_file($file) ? trim((string) file_get_contents($file)) : null);
    }
}
