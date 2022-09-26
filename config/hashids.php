<?php

/**
 * Copyright (c) Vincent Klaiber.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://github.com/vinkla/laravel-hashids
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the connections below you wish to use as
    | your default connection for all work. Of course, you may use many
    | connections at once using the manager class.
    |
    */

    'default' => 'main',

    /*
    |--------------------------------------------------------------------------
    | Hashids Connections
    |--------------------------------------------------------------------------
    |
    | Here are each of the connections setup for your application. Example
    | configuration has been included, but you may add as many connections as
    | you would like.
    |
    */

    'connections' => [

        'main' => [
            'salt' => 'your-salt-string',
            'length' => 'your-length-integer',
        ],

        'checkout' => [
            'salt' => 'ch3ck0u7',
            'length' => '64',
        ],

        'customer' => [
            'salt' => 'cu5t0m3r`',
            'length' => '16',
        ],

        'merchant' => [
            'salt' => 'm3rch4nt',
            'length' => '16',
        ],

        'merchant_user' => [
            'salt' => 'm3rchan7_us3r',
            'length' => '16',
        ],

        'order' => [
            'salt' => '0rd3r',
            'length' => '16',
        ],

        'subscription' => [
            'salt' => '5ub5cr1pt10n',
            'length' => '16',
        ],

        'user' => [
            'salt' => 'u53r',
            'lenght' => '16',
        ]

    ],

];
