<?php

return [
    'base_url' => rtrim((string) env('APIHUB_BASE_URL', config('services.apihub.base_url', 'http://apihub.services')), '/'),
    'client_id' => env('APIHUB_CLIENT_API_ID', env('APIHUB_API_KEY', env('API_KEY'))),
    'client_secret' => env('APIHUB_CLIENT_API_SECRET', env('APIHUB_API_SECRET', env('SECRET_KEY'))),
    'path' => env('APIHUB_CIBIL_PATH', 'v3/cibil'),
    'mock' => filter_var(env('APIHUB_CIBIL_MOCK', false), FILTER_VALIDATE_BOOLEAN),
    'mock_file' => env('APIHUB_CIBIL_MOCK_FILE', 'resources/cibil/sample-response.json'),
    'link_ttl' => (int) env('APIHUB_CIBIL_LINK_TTL', 600),
];
