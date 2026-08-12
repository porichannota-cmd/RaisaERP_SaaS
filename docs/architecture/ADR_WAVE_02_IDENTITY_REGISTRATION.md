# ADR — WAVE 02 — IDENTITY REGISTRATION
# RAISA ERP ENTERPRISE OS

**ID:** ADR-WAVE-02-IDENTITY-REGISTRATION
**Status:** ACCEPTED / FROZEN
**Date:** 2026-08-12
**Frozen Parent:** `075ed0a10999cbd8c9f506374069b807556b8673`
**Author:** Principal Enterprise Architect

---

## Context

Wave 2 introduces Universal Registration, Identity & Profile Engine for RAISA ERP SaaS.

The existing platform has:
- Laravel/Inertia base with email-first Breeze auth scaffolding (Wave 1)
- Multi-tenant IAM/RBAC (Wave 1A)
- Media Gateway (Wave 1B)
- Database Safety Foundation (Wave 1B.3)
- OTP / Communication Provider Core (Wave 1C)

Wave 2 must not duplicate any of these. It must provide mobile-first registration, enterprise identity, progressive profile, secure KYC, and tenant provisioning boundaries.

---

## Decisions

### ADR-02-01: Mobile-First Primary Identity

**Decision:** Mobile number is the primary registration and login identity.

**Rationale:**
- Bangladesh market: near-universal mobile penetration; email penetration lower
- Wave 1C OTP engine supports SMS verification natively
- Mobile enables: OTP auth, MFS integration, WhatsApp future communication

**Consequences:**
- `users.email` made nullable via additive migration
- `users.mobile_canonical` added as UNIQUE NOT NULL for Wave 2 users (enforced at app layer; DB UNIQUE is final gate)
- Email-first users (Wave 1 era) retain full backward compatibility
- Password reset must support both email-based and mobile-OTP-based flows
- Login field accepts mobile OR email (single credential field, server disambiguates)

**Rejected alternatives:**
- Email-first with optional mobile → rejected by PA-01
- Separate mobile/email login forms → adds confusion; increases enumeration surface

---

### ADR-02-02: Enterprise User ID Format

**Decision:** `USR-{YEAR}-{8_CHAR_CRYPTOGRAPHIC_ENTROPY}`

**Rationale:**
- Single canonical platform identity not coupled to role, position, or tenant
- Immutable after creation (business identity stability)
- Non-sequential (prevents enumeration)
- Globally unique (DB UNIQUE constraint enforced)
- Human-readable for support operations

**Implementation:**
```php
EnterpriseUserIdGenerator::generate():
  year = date('Y')
  entropy = strtoupper(bin2hex(random_bytes(4)))  // 8 uppercase hex chars
  candidate = "USR-{year}-{entropy}"
  // Retry up to 5 times on DB unique violation
```

**Rejected alternatives:**
- Role-prefixed IDs (e.g. DEALER-...) → rejected by PA-02 (mixes identity with authorization)
- UUID → not human-readable
- ULID → not human-readable as business ID
- Sequential integer → enumerable, exposes scale

---

### ADR-02-03: Sensitive Data Encryption Architecture

**Decision:** All sensitive identifier data is encrypted at rest via `SensitiveDataCipherInterface`. All sensitive lookup fingerprints use `SensitiveLookupHasherInterface` (HMAC-SHA256, keyed).

**Rationale:**
- NID numbers, bank account numbers are low-entropy → raw SHA-256 is vulnerable to dictionary attack
- HMAC-SHA256 with server-held key prevents offline dictionary attack even on DB leak
- Abstraction boundary allows future KMS/Vault replacement without domain code changes
- Deterministic HMAC enables DB UNIQUE constraint (same input → same hash with same key)
- Non-deterministic encrypted ciphertext stored separately for authorized retrieval

**Key architecture:**
```
SENSITIVE_LOOKUP_SECRET → env var → HmacSha256LookupHasher
APP_KEY                 → env var → LaravelSensitiveDataCipher (Crypt facade)
```

**Key rotation strategy:**
1. Generate new `SENSITIVE_LOOKUP_SECRET` (new key)
2. Decrypt all encrypted fields with old `APP_KEY`
3. Re-encrypt with new key (batch job)
4. Re-compute all HMAC fields with new `SENSITIVE_LOOKUP_SECRET` (batch job)
5. Atomic swap of env vars + deployment
6. Verify integrity before decommissioning old keys

**Rejected alternatives:**
- Raw SHA-256 of NID → rejected by PA-04 (dictionary attack vulnerability)
- Direct `Crypt::encryptString()` calls scattered in domain code → rejected (no abstraction)
- AWS KMS in Wave 2 → deferred (not fabricated)

---

### ADR-02-04: Pre-User Media Staging Isolation

**Decision:** Option A — Dedicated `registration_identity_documents` staging table in a separate storage namespace.

**Rationale:**
- Wave 1B `MediaUploadService` calls `ActiveTenantContext::get()` → throws without tenant
- `media_assets.tenant_id NOT NULL` with FK to `tenants` → cannot hold pre-tenant documents
- `MediaStorageRouter::generatePath()` requires tenant ID → incompatible with pre-user flows
- Dedicated staging table: no Wave 1B invariant weakened; clean isolation; deterministic cleanup

**Security properties of staging table:**
- Session-token authenticated (HMAC-validated)
- OTP_VERIFIED state required before any upload
- Storage namespace isolated from tenant media
- Short expiry (session TTL = 15 min; cleanup runs 24h after expiry)
- Cross-session access denied (filtered by `registration_session_id`)
- Claim/rebind on user creation moves documents to `media_assets` with valid context

**Rejected alternatives:**
- Nullable `tenant_id` in `media_assets` → violates FK constraint; weakens certified invariants
- Fake/partial User created to own media → pollutes `users` table with phantom records

---

### ADR-02-05: Account State Machine Over Completion Percentage

**Decision:** Authorization uses explicit account state predicates. Completion percentage is UX-only.

**Rationale:**
- Percentages are ambiguous and manipulable; "80%" could mean different things per role/category
- Explicit states are auditable, testable, and unambiguous
- Default DENY must be preserved; percentage gating creates gray areas

**Canonical states:**
```
PENDING_MOBILE_VERIFICATION → MOBILE_VERIFIED → PROFILE_INCOMPLETE
→ PENDING_APPROVAL → ACTIVE / REJECTED
ACTIVE ↔ SUSPENDED; ACTIVE → BLOCKED; BLOCKED → ACTIVE (exceptional only)
```

**Access policy:** `ProfileCompletionPolicy` / `EligibilityPolicy` evaluate explicit prerequisites, not percentages.

---

### ADR-02-06: Tenant Provisioning Boundary

**Decision:** No tenant created during Stage 1. `TenantProvisioningService` is the only tenant creation path.

**Rationale:**
- Individual users (customers, agents) may never own a tenant
- Tenant creation must be gated by business verification + approval
- Atomic transaction: Tenant + TenantMembership + MembershipRole in one transaction
- Idempotency prevents duplicate tenants on retry

---

### ADR-02-07: OCR / Porichoy Interface-Only Boundary

**Decision:** Null implementations for Wave 2. Real implementations deferred pending credentials/contract.

**Rationale:**
- No OCR engine exists in repository (confirmed by forensic search)
- No Porichoy SDK exists (confirmed)
- Fabricating these would constitute false certification
- Interface boundaries enable future implementation without domain code changes

**Verification states (truthful):**
```
NID: UNVERIFIED → PENDING → VERIFIED | REJECTED | EXPIRED
Porichoy: NOT_CONFIGURED → PENDING → VERIFIED | FAILED | MANUAL_REVIEW_REQUIRED
OCR: NOT_CONFIGURED → EXTRACTED_UNVERIFIED | FAILED | MANUAL_ENTRY_REQUIRED
```

---

## Test Architecture

### Mandatory Test Matrix

#### Group A — Registration Session
- `test_session_created_with_correct_status_on_initiation`
- `test_session_expires_after_ttl`
- `test_invalid_session_token_rejected`
- `test_consumed_session_cannot_be_reused`
- `test_otp_replay_rejected_via_consumed_status`
- `test_cross_session_document_access_denied`

#### Group B — OTP Integration
- `test_wave1c_otp_service_reused_not_duplicated`
- `test_registration_mobile_purpose_isolated_from_login_purpose`
- `test_brute_force_locks_otp_after_max_attempts`
- `test_resend_cooldown_enforced`

#### Group C — User Identity
- `test_bd_mobile_normalized_to_e164`
- `test_01_prefix_mobile_normalized_correctly`
- `test_plus880_mobile_normalized_correctly`
- `test_duplicate_mobile_rejected_by_db_constraint`
- `test_concurrent_same_mobile_registration_one_wins`
- `test_email_is_optional_in_stage_1`
- `test_non_null_duplicate_email_rejected`
- `test_enterprise_user_id_globally_unique`
- `test_enterprise_user_id_matches_format_USR_YEAR_ENTROPY`

#### Group D — Sensitive Identity
- `test_nid_number_not_stored_as_plaintext`
- `test_nid_hmac_fingerprint_correct_and_unique`
- `test_nid_number_not_in_logs`
- `test_nid_not_exposed_in_api_response`
- `test_duplicate_nid_hmac_rejected`
- `test_account_number_encrypted_not_plaintext`

#### Group E — Pre-User Media
- `test_upload_rejected_without_session_token`
- `test_upload_rejected_if_session_not_otp_verified`
- `test_disallowed_document_type_rejected`
- `test_cross_session_document_access_denied`
- `test_documents_claimed_on_user_creation`
- `test_expired_session_documents_scheduled_for_purge`

#### Group F — Account State
- `test_mobile_verified_user_can_access_onboarding`
- `test_mobile_verified_user_cannot_access_privileged_endpoints`
- `test_pending_approval_cannot_access_business_operations`
- `test_active_user_still_requires_iam_authorization`
- `test_suspended_user_cannot_authenticate`
- `test_blocked_user_cannot_authenticate`

#### Group G — IAM
- `test_client_cannot_inject_registration_source`
- `test_client_cannot_inject_role_in_registration`
- `test_client_cannot_inject_tenant_id_in_registration`
- `test_privilege_escalation_via_registration_payload_rejected`

#### Group H — Legacy Auth
- `test_existing_email_password_login_preserved`
- `test_new_mobile_password_login_works`
- `test_post_register_cannot_bypass_wave2_otp_flow`

#### Group I — Database
- `test_migrations_run_on_mariadb`
- `test_migrations_run_on_mysql8_ci`
- `test_email_nullable_unique_constraint_correct`
- `test_mobile_canonical_unique_constraint_correct`
- `test_migration_rollback_clean`

#### Group J — Regression
- `test_wave1c_tests_unmodified_pass`
- `test_wave1b_tests_unmodified_pass`
- `test_wave1b3_tests_unmodified_pass`
- `test_wave1a_tests_unmodified_pass`
- `test_artisan_db_safety_check_still_correct`
- `test_frontend_build_passes`
- `test_php_lint_passes`

---

## Key Lifecycle

```
┌─────────────────────────────────────────────┐
│ APP_KEY (Laravel encryption)                │
│   → LaravelSensitiveDataCipher             │
│   → Used for: encrypt/decrypt of NID,      │
│     account numbers, trade license, etc.   │
│                                            │
│ SENSITIVE_LOOKUP_SECRET (dedicated env)    │
│   → HmacSha256LookupHasher                │
│   → Used for: nid_number_hmac,            │
│     account_number_hmac, etc.             │
│                                            │
│ SESSION_HMAC_SECRET (dedicated env)        │
│   → Registration session token HMAC       │
│   → Used for: session_token_hash          │
└─────────────────────────────────────────────┘

Key Rotation:
1. Generate new key values
2. Batch re-encrypt / re-HMAC all records
3. Atomic deploy with new key
4. Verify integrity
5. Decommission old key

KMS/Vault: DEFERRED — adapter pattern in place via interfaces
```

---

## Log Redaction Policy

```php
// FORBIDDEN in any log statement:
Log::info('...', ['nid' => $plainNid]);          // NEVER
Log::info('...', ['mobile' => $canonical]);       // NEVER
Log::info('...', ['account_number' => $acct]);    // NEVER

// REQUIRED:
Log::info('...', ['mobile' => DestinationNormalizer::maskMobile($canonical)]);
Log::info('...', ['nid' => '****' . substr($nid, -4)]);
// Or use SensitiveDataLogger::redact($nid) helper
```

---

## Mass Assignment Protection

All new models must declare:
```php
protected $fillable = [...]; // explicit whitelist
// OR
protected $guarded = ['*']; // guarded all — only explicit assignment
```

Server-computed fields must NEVER appear in `$fillable`:
- `enterprise_user_id`
- `account_status` (only updated via state machine methods)
- `mobile_verified_at` (only set by OtpService verification)
- `registration_source` (only set by server context resolution)

---

## References

- Wave 1 Commit: `a674e12`
- Wave 1A Commit: `0d5a46b`
- Wave 1B.3 Commit: `bd0f25dbd884c3515b4cef62e48825650cebb66b`
- Wave 1B Commit: `d97401a82728b4a04cc29f7aab3bd05b78371e6e`
- Wave 1C Commit (Frozen Parent): `075ed0a10999cbd8c9f506374069b807556b8673`
- Architecture Freeze Report: `docs/implementation/WAVE_02_ARCHITECTURE_FREEZE_REPORT.md`
- Threat Model: `docs/security/WAVE_02_REGISTRATION_THREAT_MODEL.md`
