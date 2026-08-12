# RAISA ERP ENTERPRISE OS
# WAVE 2 — REGISTRATION THREAT MODEL
## Security Architecture Document

**Status:** ACCEPTED / FROZEN
**Frozen Parent:** `075ed0a10999cbd8c9f506374069b807556b8673`
**Date:** 2026-08-12
**Classification:** SECURITY-SENSITIVE — Internal

---

## Scope

This threat model covers the Wave 2 Universal Registration, Identity & Profile Engine.

Certified components (Wave 1 / 1A / 1B / 1B.3 / 1C) are considered trusted within their defined invariants. This document covers new attack surfaces introduced by Wave 2.

---

## Trust Boundaries

```
[External Client Browser/App]
        ↓ HTTPS only; CSRF on web routes
[Laravel HTTP Layer — Middleware / Rate Limiting]
        ↓
[Registration API / Profile API Controllers]
        ↓
[RegistrationSessionService — session token validation]
        ↓
[OtpService (Wave 1C) / RegistrationIdentityDocumentService / EnterpriseUserIdGenerator]
        ↓
[User model / TenantProvisioningService / AuthorizationGrantService (Wave 1A)]
        ↓
[Database — raisa_erp (PROTECTED)]
        ↓
[Encrypted at-rest fields: nid, account_number, trade_license, etc.]
```

---

## Threat Catalog

### T1 — OTP Replay Attack
- **Threat:** Attacker intercepts OTP and replays it after victim has already used it.
- **Mitigation:** `OtpService::verify()` uses `DB::lockForUpdate()` + atomically marks status CONSUMED. A consumed OTP can never be verified again — returns `alreadyUsed()` exception. Certified in Wave 1C.
- **Residual risk:** None within the certified OtpService lifecycle.

---

### T2 — OTP Brute Force
- **Threat:** Attacker systematically tries all 6-digit codes (1,000,000 combinations).
- **Mitigation:**
  - `max_attempts = 5` (config `otp.max_attempts`) → locks OTP after 5 failures
  - Rate limit: 3 OTP sends per destination per 10 minutes (config `otp.rate_limits.send_per_destination`)
  - Rate limit: 10 OTP sends per IP per hour (config `otp.rate_limits.send_per_ip`)
  - Locked OTP requires new send (which is itself rate-limited)
- **Residual risk:** LOW. With 5 attempts and short TTL (5 min), probability of brute-forcing 6-digit code is 5/1,000,000 per window.

---

### T3 — Registration Session Theft
- **Threat:** Attacker obtains a victim's session token and uses it to complete registration on their behalf.
- **Mitigation:**
  - Token is 256-bit cryptographically random (`random_bytes(32)`) — not guessable
  - Token returned once at session creation only — never re-exposed
  - Stored as HMAC-SHA256 (`SESSION_HMAC_SECRET`), not raw
  - Short TTL: 15 minutes
  - HTTPS transport: mitigates interception
  - Token is purpose-bound (only valid for registration endpoints)
  - State-bound: rejected if session status does not match required state
- **Residual risk:** LOW under HTTPS. MITM on plaintext HTTP not supported (HTTPS enforced).

---

### T4 — Session Fixation
- **Threat:** Attacker pre-creates a session and tricks victim into using it.
- **Mitigation:** Each `POST /api/v1/registration/initiate` creates a fresh session with a new random token. Previous sessions for the same mobile are not reused. Session is created server-side on initiation — no client-supplied session ID accepted.
- **Residual risk:** None.

---

### T5 — Registration Enumeration
- **Threat:** Attacker probes the registration endpoint to determine if a given mobile number is already registered.
- **Mitigation:** `POST /api/v1/registration/initiate` returns identical 200 response shape regardless of whether mobile already exists. Real duplicate rejection occurs at Step 11 (DB UNIQUE constraint during `User::create()`). Anti-enumeration is enforced at controller level — no "mobile already registered" at initiation stage.
- **Residual risk:** LOW. At account creation step, a failure does leak that the mobile is taken, but this is unavoidable at that stage; rate limiting reduces impact.

---

### T6 — Mobile Duplicate Race Condition
- **Threat:** Two concurrent registration flows for the same mobile — both attempt to create the same user.
- **Mitigation:** DB UNIQUE constraint on `users.mobile_canonical` is the final gate. Only one transaction can succeed; the other receives a DB integrity violation exception → appropriate error response. Application-layer uniqueness precheck is supplementary only.
- **Residual risk:** None at the DB layer. The loser gets an error and must restart.

---

### T7 — Enterprise User ID Collision
- **Threat:** Entropy collision — two users assigned the same `enterprise_user_id`.
- **Mitigation:** DB UNIQUE constraint on `users.enterprise_user_id`. `EnterpriseUserIdGenerator` catches constraint violation and retries up to 5 times. With 8 hex chars (4 bytes random entropy), 256^4 = ~4.3 billion possible values. Probability of collision for N users: approximately N²/(2 × 4.3B). For 1M users: ~0.01% collision across the entire space; the retry loop handles any actual collision.
- **Residual risk:** Negligible. 5 retries with true random entropy covers all practical cases.

---

### T8 — NID Duplicate Race Condition
- **Threat:** Two concurrent registrations submit the same NID.
- **Mitigation:** DB UNIQUE constraint on `user_identity_verifications.nid_number_hmac`. Only one `INSERT` succeeds; the other receives constraint violation → appropriate error response. Duplicate NID attempted by the same physical person is flagged and handled by application error logic.
- **Residual risk:** None at DB layer.

---

### T9 — NID Hash Dictionary Attack
- **Threat:** Attacker obtains DB and computes SHA-256 of all possible NID numbers to recover plaintext.
- **Mitigation:** HMAC-SHA256 with server-held `SENSITIVE_LOOKUP_SECRET` used instead of raw SHA-256. Even with the full NID number space (~170 million valid BD NIDs), an attacker without `SENSITIVE_LOOKUP_SECRET` cannot verify any precomputed hash. The key is a separate env var, not stored in DB.
- **Why raw SHA-256 is rejected:** For low-entropy inputs (a known NID numbering space), precomputed rainbow tables or brute-force enumeration of the finite NID space would succeed. HMAC prevents this.
- **Residual risk:** LOW (requires compromise of both DB and `SENSITIVE_LOOKUP_SECRET` simultaneously).

---

### T10 — Media Cross-Session Access
- **Threat:** Attacker uses a valid session token for session A to access uploaded documents from session B.
- **Mitigation:** All `registration_identity_documents` lookups are filtered by `registration_session_id`. A session token authenticates to one session only. Cross-session lookup returns 404/403.
- **Residual risk:** None.

---

### T11 — Pre-User Upload Abuse
- **Threat:** Attacker uses registration endpoint to upload arbitrary files to the server without completing registration.
- **Mitigation:**
  - Session token required (256-bit random; 15 min TTL)
  - OTP_VERIFIED state required before upload (OTP must have been successfully sent and verified)
  - Media kind whitelist: only `NID_FRONT`, `NID_BACK`, `PASSPORT`, `PROFILE_PHOTO`
  - File type/mime validation via `MediaValidationPolicy`
  - Size limits enforced
  - Rate limited: 10 uploads per session maximum
  - Expired session documents purged within 24h — no long-term storage of abuse files
- **Residual risk:** LOW. Attacker must complete OTP verification (rate-limited, requires SMS delivery control) before abusing upload.

---

### T12 — Decompression / Image Bomb Attack
- **Threat:** Attacker uploads a "zip bomb" or decompression bomb disguised as an image.
- **Mitigation:** GD image processing via `ImageOptimizerInterface` processes the image bytes, which naturally fails on malformed/bomb inputs rather than decompressing infinitely. `MediaValidationPolicy` enforces max file size before processing begins. `MalwareScannerInterface` (NullMalwareScanner in dev; real scanner in production) adds additional layer.
- **Residual risk:** MEDIUM in dev (NullMalwareScanner). Requires production malware scanner configuration.

---

### T13 — Mass Assignment Attack
- **Threat:** Client submits additional fields (e.g., `account_status: 'ACTIVE'`, `registration_source: 'INTERNAL_HR'`, `role_id: ...`) in registration payload.
- **Mitigation:** All models use explicit `$fillable`. Server-computed fields (`enterprise_user_id`, `account_status`, `registration_source`, `mobile_verified_at`) are NEVER in `$fillable`. `FormRequest` validation specifies exact allowed fields. Extra fields are ignored.
- **Residual risk:** None if `$fillable` is correctly maintained.

---

### T14 — Privilege Injection via Registration Source
- **Threat:** Client claims a privileged registration source (e.g., `INTERNAL_HR`, `DEALER`) to obtain elevated classification.
- **Mitigation:** `registration_source` is NEVER accepted from client payload. Server resolves source from: authenticated route context, invitation token binding, or system-determined logic. Client input has no influence on source.
- **Residual risk:** None.

---

### T15 — Tenant Injection
- **Threat:** Client supplies a `tenant_id` to associate with another tenant's data.
- **Mitigation:** `tenant_id` is always server-resolved from `ActiveTenantContext` or authenticated context. Client-supplied `tenant_id` is never accepted.
- **Residual risk:** None.

---

### T16 — Role Injection
- **Threat:** Client attempts to assign themselves an elevated role via registration payload.
- **Mitigation:** Role assignment is exclusively via `AuthorizationGrantService::grant()` which requires an authorized actor. Registration payload cannot specify roles. Default classification is CUSTOMER (safe) or invitation-bound (admin-pre-authorized).
- **Residual risk:** None.

---

### T17 — Approval Bypass
- **Threat:** Client attempts to advance account status to ACTIVE without going through approval.
- **Mitigation:** `account_status` transitions are server-enforced state machine methods. Client cannot supply `account_status` in any payload. `PENDING_APPROVAL → ACTIVE` transition requires explicit admin actor via `ApprovalService`.
- **Residual risk:** None.

---

### T18 — Account-State Bypass
- **Threat:** User with `MOBILE_VERIFIED` status attempts to access privileged endpoints.
- **Mitigation:** `AccountStateMiddleware` checks `account_status` before routing. Non-ACTIVE states only permit explicitly whitelisted onboarding endpoints. ACTIVE state still requires IAM `AuthorizationResolver::check()`.
- **Residual risk:** None if middleware is correctly applied to privileged route groups.

---

### T19 — Legacy /register Bypass
- **Threat:** Attacker uses legacy `POST /register` to create an account without OTP verification, registration session, or identity invariants.
- **Mitigation:** `POST /register` is disabled (returns 410 Gone) or repointed to Wave 2 initiation flow. `RegisteredUserController::store()` is not callable via any active route. Tests verify the bypass path is closed.
- **Residual risk:** None if route is correctly updated.

---

### T20 — Login Enumeration
- **Threat:** Attacker probes login endpoint with different mobiles/emails to determine which are registered.
- **Mitigation:** Generic `"The provided credentials are incorrect."` error regardless of whether mobile/email exists in the database. Response time normalized (constant-time comparison). Same error for wrong password and non-existent credential.
- **Residual risk:** LOW. Rate limiting on login endpoint provides additional protection.

---

### T21 — Sensitive Data in Logs
- **Threat:** Plaintext NID, mobile, or account numbers appear in application logs.
- **Mitigation:**
  - All NID references in log statements use masked format (`****XXXX`)
  - Mobile references use `DestinationNormalizer::maskMobile()` (already in Wave 1C)
  - Account numbers never logged at all
  - Log review in Wave 2H certification pass
- **Residual risk:** LOW — requires developer discipline + code review to maintain.

---

### T22 — Backup Exposure
- **Threat:** DB backup is compromised; attacker recovers plaintext sensitive data.
- **Mitigation:** All sensitive fields encrypted at rest (NID, account numbers, trade license, TIN). `SENSITIVE_LOOKUP_SECRET` and `APP_KEY` are NOT stored in the database — they are environment secrets. An encrypted backup without keys is not exploitable.
- **Residual risk:** LOW (requires both DB backup AND key compromise).

---

### T23 — Abandoned Registration PII Retention
- **Threat:** User begins registration but abandons it; PII (mobile, NID docs) retained indefinitely.
- **Mitigation:**
  - `registration_sessions` have `expires_at` (15 min)
  - `registration_identity_documents` have `expires_at` (matched to session)
  - Cleanup job: purges staging files + sets `is_purged = true` within 24h of session expiry
  - `registration_sessions`: soft-deleted or marked EXPIRED after 24h
- **Residual risk:** LOW. 24h maximum retention window for abandoned sessions.

---

### T24 — Orphan Media on Transaction Failure
- **Threat:** File written to storage but DB record creation fails; orphan file persists.
- **Mitigation:**
  - Certified Wave 1B `MediaUploadService` has compensating action: if `MediaAsset::create()` fails, the storage file is deleted (see MediaUploadService lines 135–145)
  - `RegistrationIdentityDocumentService` follows the same pattern for staging uploads
  - Cleanup job as second line of defense
- **Residual risk:** VERY LOW. Double-compensating action + cleanup job.

---

### T25 — Transaction Partial Failure at User Creation
- **Threat:** User created but documents not claimed, or session not consumed → inconsistent state.
- **Mitigation:** User creation (Step 11) uses a DB transaction wrapping:
  - `User::create()`
  - `RegistrationIdentityDocumentService::claimDocuments(user)` (inside transaction)
  - `RegistrationSession::markConsumed()` (inside transaction)
  If any step fails → full rollback → user not created → session remains valid for retry.
- **Residual risk:** None within the transaction boundary.

---

## Risk Register Summary

| Threat | Severity | Mitigation Quality | Residual Risk |
|--------|----------|-------------------|---------------|
| T1 OTP Replay | CRITICAL | CERTIFIED (Wave 1C) | NONE |
| T2 OTP Brute Force | HIGH | CERTIFIED (Wave 1C) | LOW |
| T3 Session Theft | HIGH | 256-bit random + short TTL + HMAC | LOW (HTTPS required) |
| T4 Session Fixation | HIGH | Server-generated sessions | NONE |
| T5 Enumeration | MEDIUM | Uniform response | LOW |
| T6 Mobile Race | HIGH | DB UNIQUE constraint | NONE |
| T7 Enterprise ID Collision | LOW | Retry + DB UNIQUE | NEGLIGIBLE |
| T8 NID Race | HIGH | DB UNIQUE constraint | NONE |
| T9 NID Hash Dictionary | HIGH | HMAC-SHA256 (keyed) | LOW |
| T10 Media Cross-Session | HIGH | Session-filtered lookup | NONE |
| T11 Upload Abuse | MEDIUM | OTP verified + rate limit + kind whitelist | LOW |
| T12 Image Bomb | MEDIUM | GD processing + size limit | MEDIUM (prod scanner needed) |
| T13 Mass Assignment | HIGH | Explicit $fillable | NONE |
| T14 Privilege Injection | CRITICAL | Server-resolved source | NONE |
| T15 Tenant Injection | CRITICAL | Server-resolved context | NONE |
| T16 Role Injection | CRITICAL | AuthorizationGrantService | NONE |
| T17 Approval Bypass | CRITICAL | State machine + ApprovalService | NONE |
| T18 Account-State Bypass | CRITICAL | AccountStateMiddleware | NONE |
| T19 Legacy Register Bypass | CRITICAL | Route disabled (410) + tests | NONE |
| T20 Login Enumeration | MEDIUM | Generic error message | LOW |
| T21 Sensitive Logs | HIGH | Log masking policy | LOW |
| T22 Backup Exposure | HIGH | Encryption at rest | LOW |
| T23 Abandoned PII | MEDIUM | 24h cleanup job | LOW |
| T24 Orphan Media | MEDIUM | Compensating action + cleanup | VERY LOW |
| T25 Transaction Failure | HIGH | DB transaction wrapping all steps | NONE |

---

## Security Controls Inventory

| Control | Implementation |
|---------|---------------|
| Transport security | HTTPS (platform-enforced) |
| CSRF protection | Laravel CSRF on all web routes |
| Session token | 256-bit random; HMAC-stored; 15min TTL |
| OTP security | Wave 1C certified (hash-stored; locked after 5 attempts) |
| Rate limiting | Wave 1C OTP limits + registration-specific IP limits |
| Mobile uniqueness | DB UNIQUE constraint (final gate) |
| NID uniqueness | DB UNIQUE on HMAC fingerprint |
| NID encryption | AES-256-CBC via LaravelSensitiveDataCipher (APP_KEY) |
| NID lookup | HMAC-SHA256 (SENSITIVE_LOOKUP_SECRET) |
| Bank account encryption | AES-256-CBC + HMAC lookup |
| Log masking | DestinationNormalizer::maskMobile() + NID masking helpers |
| Mass assignment | Explicit $fillable on all models |
| Server-resolved source | registration_source never client-supplied |
| Server-resolved tenant | tenant_id never client-supplied |
| Role isolation | AuthorizationGrantService is the only grant path |
| State machine | account_status transitions server-enforced |
| State middleware | AccountStateMiddleware gates all non-onboarding endpoints |
| Legacy route | POST /register disabled (410) |
| Pre-user media isolation | Dedicated staging table + namespace |
| Media kind whitelist | Only NID_FRONT/NID_BACK/PASSPORT/PROFILE_PHOTO in Stage 1 |
| Cleanup job | Expired sessions/docs purged within 24h |
| Transaction integrity | DB transactions wrap multi-step user creation |
| Compensating actions | Orphan file deletion on DB failure |

---

## Out of Scope (Deferred)

- Production malware scanner configuration (NullMalwareScanner in dev)
- Production Redis for session/rate-limit storage
- MIM SMS live credentials
- Porichoy govt verification
- OCR engine
- AWS KMS / HashiCorp Vault key management
- WhatsApp 2FA
- Push notification security
- Penetration testing (recommended before production launch)

---

## Review Schedule

- Wave 2H certification: full threat model re-review against implemented code
- Pre-production: external penetration test recommended
- Post-launch: quarterly security review
