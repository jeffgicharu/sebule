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
    | Selling
    |--------------------------------------------------------------------------
    |
    | Prices include VAT at this rate, in hundredths of a percent. Stock is
    | held for an order for a few minutes while the customer pays. A piece
    | that is made to order has no stock, so one order may take up to a set
    | number of it.
    |
    */

    'vat_rate' => 1600,

    'hold_minutes' => (int) env('STORE_HOLD_MINUTES', 15),

    'made_to_order_limit' => 10,

    'cart_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Test payments
    |--------------------------------------------------------------------------
    |
    | When true, an order can be completed without money changing hands, for
    | a visitor with no M-Pesa line or when M-Pesa cannot be reached.
    |
    */

    'test_payments' => (bool) env('STORE_TEST_PAYMENTS', false),

    /*
    |--------------------------------------------------------------------------
    | Email to customers
    |--------------------------------------------------------------------------
    |
    | An order email goes to whatever address was typed at checkout, so sending
    | is rationed: for the whole store each day, and for any one address.
    | Replies go to the reply address when there is one.
    |
    */

    'email' => [
        'daily_limit' => (int) env('STORE_EMAIL_DAILY_LIMIT', 80),
        'address_daily_limit' => (int) env('STORE_EMAIL_ADDRESS_DAILY_LIMIT', 12),
        'reply_to' => env('STORE_REPLY_TO'),
    ],

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
