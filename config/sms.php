<?php

/**
 * SMS delivery.
 *
 * No gateway account exists for this system yet, so the default driver is
 * "log": a message is written to the log with its full text and recipient and
 * nothing is sent. That is deliberate. A stub that silently swallows messages
 * and reports success would have the recruitment module claiming it had told
 * an applicant something it never told them.
 *
 * To go live, set SMS_DRIVER=http and fill in the gateway settings below.
 * The http driver is written against the shape every Ugandan bulk-SMS
 * provider uses (EgoSMS, Africa's Talking, Speda and the rest): one POST or
 * GET with a username, a password or key, the recipient and the text.
 */
return [

    'driver' => env('SMS_DRIVER', 'log'),

    'from' => env('SMS_SENDER_ID', 'MASTERMIND'),

    /*
     * The country this system sends to. Local numbers are typed as
     * 0772xxxxxx; gateways want 256772xxxxxx. Normalising happens in one
     * place so no caller has to think about it.
     */
    'country_code' => env('SMS_COUNTRY_CODE', '256'),

    'http' => [
        'url'      => env('SMS_URL'),
        'method'   => env('SMS_METHOD', 'POST'),
        'username' => env('SMS_USERNAME'),
        'password' => env('SMS_PASSWORD'),

        /*
         * Which parameter names this gateway expects. Only the field names
         * change between providers, so they live here rather than in code.
         */
        'fields' => [
            'username' => env('SMS_FIELD_USERNAME', 'username'),
            'password' => env('SMS_FIELD_PASSWORD', 'password'),
            'to'       => env('SMS_FIELD_TO', 'number'),
            'message'  => env('SMS_FIELD_MESSAGE', 'message'),
            'from'     => env('SMS_FIELD_FROM', 'sender'),
        ],

        /* Extra fixed parameters some gateways require, as key=value&key=value. */
        'extra' => env('SMS_EXTRA_PARAMS'),

        'timeout' => (int) env('SMS_TIMEOUT', 10),
    ],
];
