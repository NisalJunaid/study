<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('OPENAI_GRADING_MODEL', 'gpt-4.1-mini'),
    'timeout' => (int) env('OPENAI_TIMEOUT_SECONDS', 30),
    'confidence_manual_review_threshold' => (float) env('OPENAI_CONFIDENCE_MANUAL_REVIEW_THRESHOLD', 0.55),
    'queue' => env('OPENAI_GRADING_QUEUE', 'default'),
];
