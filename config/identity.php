<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Identity Providers
    |--------------------------------------------------------------------------
    |
    | Defines the active providers for identity extraction and verification.
    | Valid options for both: 'null'
    |
    | Currently, live OCR and Porichoy are not enabled for Wave 2E.
    |
    */

    'extraction_provider' => env('IDENTITY_EXTRACTION_PROVIDER', 'null'),
    'verification_provider' => env('IDENTITY_VERIFICATION_PROVIDER', 'null'),
];
