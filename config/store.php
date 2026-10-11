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
    | Demonstration
    |--------------------------------------------------------------------------
    |
    | When true, visitors can be let into marked demonstration accounts, and
    | the orders and customers are replaced each night with a fresh set. Never
    | switch this on for a store that trades.
    |
    */

    'demo' => (bool) env('STORE_DEMO', false),

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
    | Alerts
    |--------------------------------------------------------------------------
    |
    | Where to email when a page errors, a job fails or a backup does not
    | run. With no address, alerts are written to the log only.
    |
    */

    'alerts' => [
        'email' => env('STORE_ALERT_EMAIL'),
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

    /*
    |--------------------------------------------------------------------------
    | Reaching the store
    |--------------------------------------------------------------------------
    |
    | The number customers call and the WhatsApp number their chats open with,
    | both as 2547XXXXXXXX. Either may be empty, and then it is not shown.
    | Staff set these, and everything else in this file that a store would
    | change in the course of trading, in the back office.
    |
    */

    'phone' => env('STORE_PHONE'),

    'whatsapp' => env('STORE_WHATSAPP'),

    /*
    |--------------------------------------------------------------------------
    | Home page
    |--------------------------------------------------------------------------
    |
    | The words on the home page, until staff change them in the back office.
    |
    */

    'home' => [
        'hero' => [
            'eyebrow' => 'Made in Kenya',
            'heading' => 'Furniture for the way Kenya lives.',
            'text' => 'Designed in Nairobi and built in solid mvule, mahogany and Meru oak. Delivered to your door anywhere in the country.',
        ],
        'banner' => [
            'eyebrow' => 'Dining',
            'heading' => 'A table worth sitting at for hours.',
            'link' => 'Shop dining tables and chairs',
        ],
        'promises' => [
            ['heading' => 'Delivered across Kenya', 'text' => 'Our own crew in Nairobi, and trusted carriers to every county.'],
            ['heading' => 'Pay with M-Pesa', 'text' => 'A prompt on your phone at checkout. Every price includes VAT.'],
            ['heading' => 'Made to order', 'text' => 'Larger pieces are built for you in three to six weeks.'],
            ['heading' => 'Guaranteed', 'text' => 'Up to three years on solid wood furniture.'],
        ],
    ],

];
