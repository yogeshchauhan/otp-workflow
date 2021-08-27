<?php

return [

    'useTenants' => false,

    'secret_key' => "Jgu2020It",

    'secret_iv' => 'JGU-2020-IT-TOU)',

    'baseUrl' => 'https://zero.jgu.edu.in',

    'sns_key' => env('AWS_ACCESS_KEY_ID'),

    'sns_secret' => env('AWS_SECRET_ACCESS_KEY'),
        
    'sns_region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
];