<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment Policy Configuration
    |--------------------------------------------------------------------------
    */

    // Allow early payments (payments before due date)
    'allow_early_payment' => env('PAYMENT_ALLOW_EARLY', true),

    // Maximum allowed difference between expected amount and paid amount
    'amount_tolerance' => env('PAYMENT_AMOUNT_TOLERANCE', 0.01),

    // Auto-approve payments within tolerance
    'auto_approve_exact_amount' => env('PAYMENT_AUTO_APPROVE_EXACT', true),

    // Rate limiting for payment uploads (requests per minute)
    'rate_limit_per_minute' => env('PAYMENT_RATE_LIMIT', 6),

    // File upload settings
    'max_file_size_kb' => env('PAYMENT_MAX_FILE_SIZE', 5120), // 5MB
    'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png'],

    // Idempotency key expiration (in minutes)
    'idempotency_expiration' => env('PAYMENT_IDEMPOTENCY_EXPIRATION', 1440), // 24 hours
];