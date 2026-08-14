# ADR: Identity Verification Provider Foundation

## Context
Wave 2E requires establishing an abstraction layer for identity document extraction (OCR) and government identity verification (Porichoy). This layer must safely handle sensitive National Identity Data (NID) and securely audit verification attempts without compromising the "fail-closed" methodology of the platform. Live API credentials are not yet authorized.

## Decision
We will construct a dual-layer provider abstraction using `IdentityDocumentExtractionInterface` and `IdentityVerificationProviderInterface`.
Until explicit authorization is granted, these will be strictly bound to `NullIdentityDocumentExtractionProvider` and `NullIdentityVerificationProvider`.

1. **Extraction Fallback:** The Null extractor will unconditionally return `NOT_AVAILABLE`.
2. **Verification Fallback:** The Null verifier will unconditionally return `MANUAL_REVIEW_REQUIRED`.

We will implement two unified tracking tables:
- `user_identity_verifications`: Authoritative, single-record-per-user tracking the current identity state.
- `identity_verification_attempts`: Append-only, sanitized history of every extraction/verification attempt.

## Security Controls
- **Data Protection:** `nid_number` must be encrypted at rest using `SensitiveDataCipherInterface`. Deduplication will rely entirely on a blind HMAC digest via `SensitiveLookupHasherInterface`. Plaintext NID is strictly prohibited in logs and the attempt history schema.
- **Fail-Closed State:** Verification operations that throw an exception must immediately trigger a rollback, safely transitioning the tracked state to `PROVIDER_ERROR` or preserving the previous state.
- **Locking:** Once a verification record achieves `VERIFIED` status, it must be locked against accidental downgrades from subsequent failed attempts or null provider fallbacks.

## Consequences
- The system is insulated from vendor lock-in.
- Identity extraction and verification processes can be unit tested predictably using the Null providers.
- Identity records remain secure and free from unencrypted PII leakage.
- Live features (OCR, Porichoy) are deferred until explicit Wave approval and configuration.
