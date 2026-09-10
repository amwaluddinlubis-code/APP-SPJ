<?php

return [
    'host' => env('OLLAMA_HOST', 'http://localhost:11434'),
    'model' => env('OLLAMA_MODEL', 'qwen2.5-coder:1.5b'),
    'timeout' => (int) env('OLLAMA_TIMEOUT', 180),
    'max_tokens' => (int) env('OLLAMA_MAX_TOKENS', 150),
];
