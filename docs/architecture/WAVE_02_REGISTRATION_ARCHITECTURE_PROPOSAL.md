# RAISA ERP ENTERPRISE OS
# WAVE 2 — REGISTRATION ARCHITECTURE PROPOSAL
## UPDATED: All 7 PA Questions RESOLVED / FROZEN

**Status:** PA DECISIONS RESOLVED — ARCHITECTURE FROZEN — AWAITING WAVE 2A IMPLEMENTATION APPROVAL
**Frozen Parent Baseline:** `075ed0a10999cbd8c9f506374069b807556b8673`
**Branch:** `main`
**Updated:** 2026-08-12

> DO NOT IMPLEMENT. DO NOT MIGRATE. DO NOT COMMIT. Awaiting Wave 2A implementation plan approval.

---

## PA DECISIONS — ALL RESOLVED

| # | Decision | Status |
|---|----------|--------|
| PA-01 | Email strategy | RESOLVED: email nullable; mobile is primary identity |
| PA-02 | Enterprise User ID | RESOLVED: `USR-{YEAR}-{8CHAR}` — global, immutable, no org semantics |
| PA-03 | Legacy /register | RESOLVED: POST /register disabled (410); GET /register → Wave 2 wizard |
| PA-04 | Encryption | RESOLVED: `SensitiveDataCipherInterface` + `SensitiveLookupHasherInterface` (HMAC-keyed) |
| PA-05 | Profile completion gating | RESOLVED: percentage = UX only; authorization = explicit account states |
| PA-06 | Tenant creation | RESOLVED: no tenant in Stage 1; `TenantProvisioningService` is the only path |
| PA-07 | Pre-user media | RESOLVED: Option A — `registration_identity_documents` staging table |

For full decision details see:
- `docs/implementation/WAVE_02_ARCHITECTURE_FREEZE_REPORT.md`
- `docs/architecture/ADR_WAVE_02_IDENTITY_REGISTRATION.md`

---

## Repository Discovery Summary

### Certified Foundations (All Verified Reusable)

| Foundation | Key Assets | Wave 2 Use |
|-----------|-----------|-----------|
| Wave 1C OTP | `OtpService`, `OtpRecord`, `DestinationNormalizer` | Stage 1 mobile OTP; `OtpPurpose::REGISTRATION_MOBILE` already present |
| Wave 1B Media | `MediaUploadService`, `MediaKind::IDENTITY_DOCUMENT` | Stage 2 document uploads (post-user-creation only) |
| Wave 1A IAM | `Tenant`, `TenantMembership`, `Role`, `AuthorizationGrantService` | Membership + role assignment |
| Wave 1B.3 Safety | `DatabaseSafetyPolicy` | All Wave 2 migrations |

### Critical Sources Verified

- `MediaUploadService::ingest()` calls `ActiveTenantContext::get()` — **THROWS without tenant** → staging table required
- `media_assets.tenant_id NOT NULL` with FK constraint → **cannot hold pre-user docs**
- `OutboxPublisher::publish()` calls `ActiveTenantContext::get()` → **platform events deferred**
- `users.email UNIQUE NOT NULL` (frozen) → **additive migration to nullable required**
- `OtpPurpose::REGISTRATION_MOBILE` and `REGISTRATION_EMAIL` already present in enum ✅

---

## Two-Stage Registration Contract (FINAL)

### Stage 1 — Platform Identity

```
① Mobile + country code submitted
② DestinationNormalizer → E.164 canonical
③ RegistrationSession created (ULID, token HMAC, 15min TTL)
④ OtpService::send(REGISTRATION_MOBILE, SMS, tenantId: null, userId: null)
⑤ OtpService::verify(otpId, code, REGISTRATION_MOBILE)
   → RegistrationSession.status = OTP_VERIFIED
⑥ NID front/back + profile photo uploaded via staging boundary
   (RegistrationIdentityDocumentService — NOT MediaUploadService directly)
⑦ Password submitted (confirmed; Password::defaults() complexity)
   PIN: POLICY_DECISION_PENDING — not in Wave 2
⑧ Email submitted (optional)
⑨ DB Transaction:
   a. EnterpriseUserIdGenerator::generate() → USR-YYYY-XXXXXXXX
   b. User::create() — mobile_canonical UNIQUE gate (final)
   c. RegistrationIdentityDocumentService::claimDocuments(user)
   d. RegistrationSession::markConsumed()
⑩ Auth::login($user)
⑪ account_status = MOBILE_VERIFIED (→ PROFILE_INCOMPLETE if Stage 2 not started)
⑫ Limited authenticated access granted
```

### Stage 2 — Profile Completion

```
Sections (policy-driven by source/category):
  PERSONAL → CONTACT → ADDRESS → IDENTITY → EMPLOYMENT
  BUSINESS → BANKING → MFS → DOCUMENTS → CONSENTS

ProfileCompletionPolicy evaluates explicit prerequisites:
  → required sections complete + required docs verified + required approval
  → account_status = PENDING_APPROVAL

TenantProvisioningService::provision() (for business accounts):
  → transaction: Tenant + TenantMembership + MembershipRole
  → account_status = ACTIVE

Individual customers: ACTIVE without tenant provisioning.
```

---

## Authoritative Table Inventory (11 New Tables)

> Earlier proposal stated "13 tables" — that count was imprecise. **Correct count: 11 new tables.**

| # | Table | Scope | Sensitive | Purpose |
|---|-------|-------|-----------|---------|
| A | `users` additive columns | Platform | mobile_canonical | Registration fields added |
| 1 | `registration_sessions` | Platform | mobile_canonical, session_token_hash | Pre-user OTP session |
| 2 | `registration_identity_documents` | Platform staging | file storage | Pre-user NID/photo staging |
| 3 | `user_identity_verifications` | Platform (user-owned) | nid_number_hmac + encrypted | KYC data |
| 4 | `user_profiles` | Platform (user-owned) | — | Base profile |
| 5 | `user_contact_details` | Platform (user-owned) | — | Supplementary contact |
| 6 | `user_addresses` | Platform (user-owned) | geocode (consent-gated) | Address types |
| 7 | `user_bank_accounts` | Platform (user-owned) | account_number encrypted+HMAC | Banking |
| 8 | `user_mfs_accounts` | Platform (user-owned) | mobile_canonical | MFS providers |
| 9 | `user_legal_identifiers` | Platform (user-owned) | identifier encrypted+HMAC | Legal docs |
| 10 | `business_profiles` | Platform→Tenant | trade_license/TIN/BIN encrypted+HMAC | Business entity |
| 11 | `user_consents` | Platform (user-owned) | — | Digital consent records |

---

## Security Architecture (FINAL)

| Layer | Design |
|-------|--------|
| Session token | `random_bytes(32)` → base64url; stored as HMAC-SHA256 |
| NID storage | Encrypted (`SensitiveDataCipherInterface`) + HMAC fingerprint (`SensitiveLookupHasherInterface`) |
| Account numbers | Same as NID |
| Mobile (canonical) | Plaintext stored; MASKED in all logs |
| Server-resolved fields | `registration_source`, `account_status`, `tenant_id` — never client-supplied |
| Mass assignment | Explicit `$fillable` on all new models |
| Threat model | 25 threats catalogued in `docs/security/WAVE_02_REGISTRATION_THREAT_MODEL.md` |

---

## Proposed Implementation Sequence

| Sub-Wave | Scope | Parent |
|----------|-------|--------|
| Wave 2A | Schema foundation (users columns, registration_sessions, staging table) | 075ed0a |
| Wave 2B | Stage 1 API + mobile-first auth transition | Wave 2A SHA |
| Wave 2C | Pre-user media boundary implementation | Wave 2B SHA |
| Wave 2D | Progressive profile engine (5 profile tables) | Wave 2C SHA |
| Wave 2E | Identity provider interfaces (OCR/Porichoy null impls) | Wave 2D SHA |
| Wave 2F | Business profile + TenantProvisioningService | Wave 2E SHA |
| Wave 2G | Approval / account state enforcement | Wave 2F SHA |
| Wave 2H | Security hardening + full certification | Wave 2G SHA |

---

## Deferred Items

| Item | Status |
|------|--------|
| Security PIN | POLICY_DECISION_PENDING — PA must define semantics |
| MIM SMS live | CONFIGURATION PENDING |
| Govt Porichoy | CONFIGURATION / CONTRACT PENDING |
| OCR engine | NOT PRESENT — interface only in Wave 2 |
| AWS KMS / Vault | DEFERRED — adapter pattern in place |
| Redis session | PRODUCTION DEPLOYMENT requirement |
| Outbox for platform events | `outbox_events.tenant_id NOT NULL` — deferred |

---

## References

- Freeze Report: `docs/implementation/WAVE_02_ARCHITECTURE_FREEZE_REPORT.md`
- ADR: `docs/architecture/ADR_WAVE_02_IDENTITY_REGISTRATION.md`
- Threat Model: `docs/security/WAVE_02_REGISTRATION_THREAT_MODEL.md`
- Preflight Report: `docs/implementation/WAVE_02_PREFLIGHT_REPORT.md`
