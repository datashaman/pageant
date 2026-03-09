<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Context Window Size
    |--------------------------------------------------------------------------
    |
    | The maximum token capacity of the model's context window. This is used
    | to determine when conversation compression should be triggered.
    |
    */

    'context_window' => env('CONVERSATION_COMPRESSION_CONTEXT_WINDOW', 200000),

    /*
    |--------------------------------------------------------------------------
    | Compression Threshold
    |--------------------------------------------------------------------------
    |
    | A decimal between 0 and 1 representing the percentage of the context
    | window that must be filled before compression is triggered. A value
    | of 0.8 means compression starts at 80% of the context window.
    |
    */

    'threshold' => env('CONVERSATION_COMPRESSION_THRESHOLD', 0.8),

    /*
    |--------------------------------------------------------------------------
    | Summary Provider
    |--------------------------------------------------------------------------
    |
    | The AI provider to use for generating conversation summaries. Use a
    | cheaper or faster model to reduce cost during compression.
    |
    */

    'summary_provider' => env('CONVERSATION_COMPRESSION_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Summary Model
    |--------------------------------------------------------------------------
    |
    | The specific model to use for generating summaries. When null, the
    | provider's default model will be used.
    |
    */

    'summary_model' => env('CONVERSATION_COMPRESSION_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Summary Tokens
    |--------------------------------------------------------------------------
    |
    | The maximum number of tokens the compression summary should contain.
    | This helps keep summaries concise and within budget.
    |
    */

    'max_summary_tokens' => env('CONVERSATION_COMPRESSION_MAX_SUMMARY_TOKENS', 500),

];
