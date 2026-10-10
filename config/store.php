<?php

use App\Support\Release;

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

    'release' => Release::id(),

    /*
    |--------------------------------------------------------------------------
    | Search engine indexing
    |--------------------------------------------------------------------------
    |
    | When false, every response asks search engines not to index it.
    |
    */

    'allow_indexing' => (bool) env('STORE_ALLOW_INDEXING', false),

    /*
    |--------------------------------------------------------------------------
    | Showroom
    |--------------------------------------------------------------------------
    |
    | Where customers can visit, shown in the footer and given to search
    | engines with the rest of the store's details.
    |
    */

    'showroom' => [
        'street' => 'Ngong Road',
        'city' => 'Nairobi',
        'country' => 'KE',
        'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'opens' => '09:00',
        'closes' => '18:00',
    ],

];
