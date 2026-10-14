<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Word List Languages
    |--------------------------------------------------------------------------
    |
    | Display names for the word lists in resources/words. Any {code}.txt file
    | in that folder is picked up automatically; list it here to give it a
    | friendly name and a position in the language picker.
    |
    */

    'languages' => [
        'en' => 'English',
        'hu' => 'Magyar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    */

    'default_language' => env('CODENAMES_DEFAULT_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Polling Interval
    |--------------------------------------------------------------------------
    |
    | How often (in milliseconds) each device asks the server for updates.
    |
    */

    'poll_interval' => (int) env('CODENAMES_POLL_INTERVAL', 1500),

];
