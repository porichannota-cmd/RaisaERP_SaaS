<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sensitive Lookup Secret (HMAC key for NID, bank account fingerprints)
    |--------------------------------------------------------------------------
    |
    | PA-04: Raw SHA-256 of low-entropy sensitive identifiers is rejected.
    | All lookup fingerprints use HMAC-SHA256 with this server-held secret.
    |
    | This key MUST be separate from APP_KEY and must be at least 32 characters.
    | Rotating this key requires re-computing all HMAC fields in a migration job.
    | Do NOT store a real secret here — use the environment variable.
    |
    | KMS/Vault integration is deferred; the interface allows adapter replacement.
    |
    */
    'sensitive_lookup_secret' => env('SENSITIVE_LOOKUP_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Registration Session HMAC Secret
    |--------------------------------------------------------------------------
    |
    | Key used to generate HMAC fingerprints of registration session tokens.
    | Raw session tokens are NEVER stored — only this fingerprint is persisted.
    | Must be at least 32 characters. Separate from APP_KEY.
    |
    */
    'session_hmac_secret' => env('REGISTRATION_SESSION_HMAC_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Registration Session TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long a registration session is valid from initiation.
    | Default: 15 minutes (900 seconds).
    | Sessions past this TTL must be rejected and scheduled for cleanup.
    |
    */
    'session_ttl_seconds' => (int) env('REGISTRATION_SESSION_TTL', 900),

    /*
    |--------------------------------------------------------------------------
    | Staging Document Retention (seconds after session expiry)
    |--------------------------------------------------------------------------
    |
    | Staged identity documents are retained for this duration after their
    | session expires, then purged by the cleanup job (Wave 2H).
    | Default: 86400 seconds (24 hours).
    |
    */
    'staging_document_retention_seconds' => (int) env('REGISTRATION_STAGING_RETENTION', 86400),

    /*
    |--------------------------------------------------------------------------
    | Enterprise User ID Retry Cap
    |--------------------------------------------------------------------------
    |
    | Maximum number of collision retries for EnterpriseUserIdGenerator.
    | With 4-byte entropy (~4.3B possibilities), collision is astronomically rare.
    | A failure after this many retries indicates a system-level problem.
    |
    */
    'enterprise_id_retry_cap' => (int) env('ENTERPRISE_ID_RETRY_CAP', 5),

];
