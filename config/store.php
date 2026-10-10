<?php

$revisionFile = dirname(__DIR__).'/REVISION';

return [

    /*
    |--------------------------------------------------------------------------
    | Release
    |--------------------------------------------------------------------------
    |
    | The commit this build was made from. The deploy pipeline writes it to a
    | REVISION file in the release, and every response carries it in the
    | X-Release header so a deploy can confirm what is being served.
    |
    */

    'release' => env('APP_RELEASE') ?: (is_file($revisionFile) ? trim((string) file_get_contents($revisionFile)) : null),

    /*
    |--------------------------------------------------------------------------
    | Search engine indexing
    |--------------------------------------------------------------------------
    |
    | When false, every response asks search engines not to index it.
    |
    */

    'allow_indexing' => (bool) env('STORE_ALLOW_INDEXING', false),

];
