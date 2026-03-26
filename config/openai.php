<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('OPENAI_GRADING_MODEL', 'gpt-4.1-mini'),
    'models' => [
        'low_cost' => env('OPENAI_MODEL_LOW_COST', env('OPENAI_GRADING_MODEL', 'gpt-4.1-mini')),
        'high_accuracy' => env('OPENAI_MODEL_HIGH_ACCURACY', 'gpt-4.1'),
        'fallback' => env('OPENAI_MODEL_FALLBACK', env('OPENAI_MODEL_HIGH_ACCURACY', 'gpt-4.1')),
    ],
    'timeout' => (int) env('OPENAI_TIMEOUT_SECONDS', 30),
    'queue' => env('OPENAI_GRADING_QUEUE', 'default'),

    'batch_size' => (int) env('OPENAI_GRADING_BATCH_SIZE', 5),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 1200),
    'max_feedback_chars' => (int) env('OPENAI_MAX_FEEDBACK_CHARS', 220),
    'confidence_manual_review_threshold' => (float) env('OPENAI_CONFIDENCE_MANUAL_REVIEW_THRESHOLD', 0.55),
    'escalation_confidence_threshold' => (float) env('OPENAI_ESCALATION_CONFIDENCE_THRESHOLD', 0.45),
    'retry_count' => (int) env('OPENAI_GRADING_RETRY_COUNT', 2),

    'enable_caching' => (bool) env('OPENAI_ENABLE_CACHING', true),
    'cache_ttl_seconds' => (int) env('OPENAI_CACHE_TTL_SECONDS', 2592000),
    'cache_version' => env('OPENAI_CACHE_VERSION', 'theory-v2'),
];
