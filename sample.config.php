<?php

use function Kipchak\Core\env;

return [
    'connections' => [
        'default' => [
            'api_key' => env('OPENAI_API_KEY', ''),
            'organization' => env('OPENAI_ORG', ''),
            'project' => env('OPENAI_PROJECT', ''),
            'base_uri' => env('OPENAI_BASE_URI', 'https://api.openai.com/v1'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o-mini')
        ]
    ]
];
