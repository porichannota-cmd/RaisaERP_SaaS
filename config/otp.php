<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OTP Length
    |--------------------------------------------------------------------------
    | The number of digits in the OTP code.
    */
    'length' => env('OTP_LENGTH', 6),

    /*
    |--------------------------------------------------------------------------
    | OTP TTL (seconds)
    |--------------------------------------------------------------------------
    | Default time-to-live for an OTP record in seconds.
    | Purpose-specific policies may override this.
    */
    'ttl' => env('OTP_TTL', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Resend Cooldown (seconds)
    |--------------------------------------------------------------------------
    | Minimum seconds required between successive OTP sends for the same
    | destination + purpose.
    */
    'resend_cooldown' => env('OTP_RESEND_COOLDOWN', 60),

    /*
    |--------------------------------------------------------------------------
    | Max Verify Attempts
    |--------------------------------------------------------------------------
    | Maximum number of incorrect verification attempts before locking the OTP.
    */
    'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    | Send rate limiting per destination (per window in seconds).
    */
    'rate_limits' => [
        'send_per_destination' => [
            'max' => env('OTP_SEND_MAX_PER_DESTINATION', 3),
            'window' => env('OTP_SEND_WINDOW_DESTINATION', 600), // 10 minutes
        ],
        'send_per_ip' => [
            'max' => env('OTP_SEND_MAX_PER_IP', 10),
            'window' => env('OTP_SEND_WINDOW_IP', 3600), // 1 hour
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default SMS Provider
    |--------------------------------------------------------------------------
    | The default SMS provider key. Must be registered in the providers array.
    | In production this must not be 'log'.
    */
    'default_sms_provider' => env('OTP_SMS_PROVIDER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Default Email Provider
    |--------------------------------------------------------------------------
    */
    'default_email_provider' => env('OTP_EMAIL_PROVIDER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | SMS Providers
    |--------------------------------------------------------------------------
    */
    'sms_providers' => [
        'log' => [
            'driver' => 'log',
        ],
        'mim' => [
            'driver' => 'mim',
            'api_url' => env('MIM_SMS_API_URL'),
            'username' => env('MIM_SMS_USERNAME'),
            'password' => env('MIM_SMS_PASSWORD'),
            'sender' => env('MIM_SMS_SENDER_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Guard
    |--------------------------------------------------------------------------
    | If true, prevents the 'log' SMS provider from being used in production.
    */
    'production_log_provider_guard' => env('OTP_PRODUCTION_LOG_GUARD', true),

    /*
    |--------------------------------------------------------------------------
    | OTP Retention (hours)
    |--------------------------------------------------------------------------
    | How long to retain expired/consumed OTP records for audit purposes.
    */
    'retention_hours' => env('OTP_RETENTION_HOURS', 48),
];
