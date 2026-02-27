<?php
// config/ai.php
return [
    'default' => env('AI_PROVIDER', 'openai'),
    'providers' => [
        'openai' => [
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => env('OPENAI_API_KEY', ''),
            'model' => 'gpt-4o',
            'embedding_model' => 'text-embedding-3-small',
        ],
        'anthropic' => [
            'base_url' => 'https://api.anthropic.com/v1',
            'api_key' => env('ANTHROPIC_API_KEY', ''),
            'model' => 'claude-sonnet-4-6',
        ],
        'grok' => [
            'base_url' => 'https://api.x.ai/v1',
            'api_key' => env('GROK_API_KEY', ''),
            'model' => 'grok-2',
        ],
    ],
];
