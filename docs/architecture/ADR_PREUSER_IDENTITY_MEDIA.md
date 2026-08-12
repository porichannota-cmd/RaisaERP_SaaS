# Architecture Decision Record: Pre-User Identity Media Staging

## Context
During Wave 2C, Raisa ERP needed a method to ingest identity verification documents (Profile Photo, NID Front, NID Back) *before* the enterprise account was completely provisioned and a tenant context existed.

Identity documents carry high compliance, privacy, and security risks. Allowing public, unauthenticated file uploads opens severe vectors for malware distribution, decompression bombs, and untethered storage bloat (orphan files).

## Decision
We implemented an **Isolated Staging Area with Strong Session Binding**, decoupled entirely from the core `MediaAssets` architecture until explicitly claimed.

1.  **Session Authorization:** Uploads are completely disallowed unless tied to a strictly authenticated `RegistrationSession`. The session must be backed by a cryptographically verified opaque token (`token_hash`) and be in an `OTP_VERIFIED` state.
2.  **Isolated Storage:** Pre-user identity documents are stored in a segregated namespace on a private disk (`registration/{session_id}/{ulid}.{ext}`). They are absolutely unreachable via public URLs.
3.  **Strict Type Normalization:** We force-compress all accepted images into `webp` using `InterventionImageOptimizer` during upload. This intrinsically neutralizes PHP/polyglot payloads embedded in image metadata/EXIF headers. Disguised files lacking valid structural headers are immediately rejected.
4.  **Dimensional Guardrails:** To protect the server's RAM from compression/decompression bombs, we introduced a preflight `MAX_PIXELS = 25,000,000` limit before any heavy optimization begins.
5.  **Transaction Compensation:** The `RegistrationIdentityDocumentService` wraps database persistence and disk I/O in a compensation block. If DB commits fail, the newly written disk file is unlinked. If disk I/O fails, DB changes are aborted.
6.  **Deferred Claim Binding:** During Stage 1 account creation (Wave 2B integration), `RegistrationIdentityDocumentClaimService` simply marks these documents as `CLAIMED` and binds the new user ID. They are *not* automatically moved to `MediaAssets`—that transition is reserved for the tenant-provisioning lifecycle phase (Stage 2/3) to ensure strict tenant boundaries are maintained.

## Consequences
*   **Positive:** The application is highly resilient to anonymous malware ingestion and storage-flooding attacks.
*   **Positive:** PII (Identity Documents) is kept off public endpoints and decoupled from tenant logic until explicit consent/tenant structure exists.
*   **Negative/Trade-off:** It creates a temporary, duplicated media lifecycle model (Staging vs. Permanent MediaAssets), requiring a specific cron job or lifecycle event to purge orphaned sessions eventually.
