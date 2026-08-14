# Wave 2E Identity Verification Provider Foundation Report

## Executive Summary
Wave 2E establishes the foundational domain models, schema, and provider abstraction interfaces required for handling National ID extraction (OCR) and subsequent identity verification (e.g., via Porichoy API). In strict adherence to our fail-closed methodology, **no live integrations have been enabled**.

## Implementation Status

*   **Extraction Contract:** `IdentityDocumentExtractionInterface` implemented.
*   **Verification Contract:** `IdentityVerificationProviderInterface` implemented.
*   **Null Extraction Provider:** `NullIdentityDocumentExtractionProvider` implemented. Returns `NOT_AVAILABLE`.
*   **Null Verification Provider:** `NullIdentityVerificationProvider` implemented. Returns `MANUAL_REVIEW_REQUIRED`.
*   **Verification Schema:** `user_identity_verifications` table created. Safe against duplication.
*   **Attempt Schema:** `identity_verification_attempts` table created. Tracks all actions.
*   **Provider Config:** Null providers are registered by default in `config/identity.php`.
*   **Document Ownership:** Enforcement explicitly verified via tests.
*   **NID Encryption:** `SensitiveDataCipherInterface` encrypts NID prior to storage.
*   **NID HMAC:** `SensitiveLookupHasherInterface` creates a safe deduplication fingerprint.
*   **NID Masking:** Only masked NIDs are dispatched to API consumers (Last 4 digits).
*   **Attempt PII Hygiene:** PII is scrubbed.
*   **Extraction Lifecycle:** Defined and tested.
*   **Verification Lifecycle:** Defined and tested.
*   **Verified-State Protection:** A successful verification locks the identity to prevent accidental downgrades.
*   **Idempotency & Retry Semantics:** Built and enforced.
*   **Provider Failure Safety:** Fail-closed exception handling guarantees safe database states.
*   **Rate Limiting:** DEFERRED.
*   **Route Authorization:** Validated via `EnforceAccountAccess` and `auth` middleware.
*   **API Privacy:** Fully maintained.

## Deferred/Not Implemented Features
*   **Unique NID Policy:** Global NID uniqueness enforcement is NOT YET FROZEN / DEFERRED.
*   **Profile Sync Status:** Automatic profile synchronization is DEFERRED.
*   **Name/DOB Comparison:** NOT IMPLEMENTED.
*   **Identity UI / Visual Status:** ROUTE RENDER VERIFIED via local test runner for `profile/index.tsx`, but full visual certification is pending.
*   **OCR Status:** NOT IMPLEMENTED.
*   **Porichoy Status:** NOT IMPLEMENTED / CONFIGURATION-CONTRACT PENDING.
*   **Manual Review Status:** Manual Review state foundation exists, but Super Admin adjudication UI is NOT IMPLEMENTED.
*   **Audit Status:** DEFERRED / ARCHITECTURE LIMITATION.
*   **Outbox Status:** DEFERRED.
*   **MySQL 8 Status:** PENDING REMOTE CI.
