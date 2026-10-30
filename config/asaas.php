<?php

return [
    'api_key' => env('ASAAS_API_KEY'),
    'environment' => env('ASAAS_ENVIRONMENT', 'sandbox'),
    'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),

    'base_url' => env('ASAAS_ENVIRONMENT', 'sandbox') === 'production'
        ? 'https://api.asaas.com/v3'
        : 'https://sandbox.asaas.com/api/v3',
];
