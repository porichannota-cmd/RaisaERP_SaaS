# RAISA ERP ENTERPRISE OS
# WAVE 2 — PRINCIPAL ARCHITECT DECISION RESOLUTION & ARCHITECTURE FREEZE REPORT

**Frozen Parent:** `075ed0a10999cbd8c9f506374069b807556b8673`
**Current HEAD:** `075ed0a10999cbd8c9f506374069b807556b8673` — BASELINE CONFIRMED
**Branch:** `main`
**Date:** 2026-08-12
**Mode:** READ-FIRST / EVIDENCE-DRIVEN / NO-REGRESSION / NO-OVERCLAIM / NO-COMMIT

---

## 1. FROZEN PARENT

`075ed0a10999cbd8c9f506374069b807556b8673` — Wave 1C certification lock. CONFIRMED.

## 2. CURRENT HEAD

`075ed0a10999cbd8c9f506374069b807556b8673` — UNCHANGED. BASELINE HOLDS.

## 3. WORKING TREE CLASSIFICATION

```
?? docs/architecture/WAVE_02_REGISTRATION_ARCHITECTURE_PROPOSAL.md  ← planning doc (untracked)
?? docs/implementation/WAVE_02_PREFLIGHT_REPORT.md                  ← planning doc (untracked)
```

**Classification:** Only architecture/planning documents are untracked.
No application code, migrations, seeds, test files, or secrets.
Working tree is CLEAN for purposes of frozen baseline integrity.

---

## 4. PA-01 — EMAIL / LOGIN DECISION

**DECISION: RESOLVED / FROZEN**

Pure mobile-first registration approved. `users.email` becomes nullable via additive migration.

**Forensic source basis:**
```php
// 0001_01_01_000000_create_users_table.php — FROZEN
$table->string('email')->unique();
// Current: NOT NULL + UNIQUE
```

**Architecture resolution:**
- Additive migration changes `email` to `nullable()` while preserving `UNIQUE` constraint
- MySQL/MariaDB support `UNIQUE` on nullable columns — NULL values do not conflict with each other (each NULL is distinct)
- Existing non-null email rows: preserved, still enforce uniqueness against other non-null values
- New mobile-first Wave 2 users: email is null at registration; may be set later
- `password_reset_tokens` references `email` as primary key — this remains valid for users who have emails
- Mobile-first users without email cannot use email password reset; a mobile-based reset flow is required (deferred, uses Wave 1C OTP)
- Do NOT create a duplicate auth stack — extend `LoginRequest` to accept mobile OR email as identifier

**Backward compatibility:**
- All existing certified users authenticated by email continue to work unchanged
- `Auth::attempt(['email' => ..., 'password' => ...])` still functions for email users
- Mobile login added as an additional credential type (resolved in section 20)

**Uniqueness invariants:**
- `users.email UNIQUE` — preserved for non-null values
- `users.mobile_canonical UNIQUE NOT NULL` — new DB constraint for Wave 2 users
- Both are globally unique across the platform (not tenant-scoped)

---

## 5. PA-02 — ENTERPRISE USER ID DECISION

**DECISION: RESOLVED / FROZEN**

**Format:** `USR-{YEAR}-{8_CHAR_CRYPTOGRAPHIC_ENTROPY}`

Example: `USR-2026-A7K9M2QX`

**Properties:**
- Globally unique — DB UNIQUE constraint on `users.enterprise_user_id`
- Immutable once issued — domain layer enforces no mutation after creation
- Server-generated only — never user-supplied, never client-influenced
- Non-sequential — entropy is `bin2hex(random_bytes(4))` uppercased (4 bytes = 8 hex chars), cryptographically random
- Collision-safe — retry loop under DB unique constraint exception (max 5 attempts, then throw)
- No tenant identifier embedded
- No role, position, or organizational data embedded
- No NID, mobile, or email data embedded

**Core invariant confirmed:**
```
USER ≠ ROLE ≠ POSITION ≠ TENANT
enterprise_user_id identifies the platform person only.
```

**Implementation service:** `App\Domain\Registration\Services\EnterpriseUserIdGenerator` — one canonical service; no duplicates.

---

## 6. PA-03 — LEGACY /register DECISION

**DECISION: RESOLVED / FROZEN**

Backward-compatible supersession. No silent deletion.

**Source confirmed (routes/auth.php — FROZEN):**
```php
Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
Route::post('register', [RegisteredUserController::class, 'store']);
```

**Resolution plan:**
- `GET /register`: Renders Inertia `auth/register` page — in Wave 2, this page becomes the mobile-first registration wizard entry. No redirect needed at route level if the Inertia page component is replaced
- `POST /register` (legacy): Must be disabled or redirected to the Wave 2 flow — the old handler bypasses OTP, mobile verification, registration session, and identity invariants. It MUST NOT be left functional
- Implementation strategy in Wave 2B: Route `POST /register` to a `LegacyRegistrationDeprecationController` that returns `410 Gone` with an explanation, OR repoint it to the new `POST /api/v1/registration/initiate`
- `RegisteredUserController::store()` must not be callable with a bypass path
- All password reset, email verification, login, and logout routes remain unchanged

**Compatibility tests required:**
- `GET /login` → still renders login page
- `POST /login` (email + password) → still works for existing users
- `GET /forgot-password` → unchanged
- `POST /forgot-password` → unchanged
- `GET /register` → now renders Wave 2 mobile-first wizard (new Inertia component)
- `POST /register` → returns 410 or is repointed to Wave 2 initiation

---

## 7. PA-04 — SENSITIVE DATA ENCRYPTION DECISION

**DECISION: RESOLVED / FROZEN**

Application encryption behind abstraction boundary. Keyed hash for lookup fingerprints. Raw SHA-256 of sensitive low-entropy data rejected.

**Required interfaces:**
```php
interface SensitiveDataCipherInterface
{
    public function encrypt(string $plaintext): string;
    public function decrypt(string $ciphertext): string;
}

interface SensitiveLookupHasherInterface
{
    public function hash(string $plaintext): string;        // returns hex
    public function verify(string $plaintext, string $hash): bool;
}
```

**Initial implementation:**
- `LaravelSensitiveDataCipher` wraps `Crypt::encryptString()` (AES-256-CBC via APP_KEY)
- `HmacSha256LookupHasher` wraps `hash_hmac('sha256', $plaintext, $serverSecret)` using a dedicated `SENSITIVE_LOOKUP_SECRET` env var (separate from APP_KEY)

**Lookup fingerprint rationale:**
- NID numbers, account numbers are low-entropy — raw SHA-256 is vulnerable to precomputation/dictionary attacks
- HMAC-SHA256 with a server-held secret prevents offline dictionary attack even if DB is leaked
- The HMAC output is deterministic (same plaintext + same secret = same hash) → enables UNIQUE constraint and lookup
- Must NOT use the encrypted ciphertext as a lookup key (ciphertext is non-deterministic per Crypt design)

**Invariants:**
- Plaintext NEVER logged
- Plaintext NEVER exposed in audit payloads
- Plaintext NEVER used as a public identifier
- Encrypted value stored for authorized retrieval via `SensitiveDataCipherInterface`
- Lookup fingerprint stored for uniqueness/search via HMAC

**Future replaceability:** Both interfaces can be backed by AWS KMS / HashiCorp Vault adapters without changing domain code. KMS/Vault integration DEFERRED — not fabricated.

**Key rotation strategy:** Documented in `ADR_WAVE_02_IDENTITY_REGISTRATION.md` section on key lifecycle.

---

## 8. PA-05 — PROFILE COMPLETION / ACCESS DECISION

**DECISION: RESOLVED / FROZEN**

Percentage-only security gating is REJECTED. Authorization uses explicit account states and predicates.

**Canonical account state enum:**
```
PENDING_MOBILE_VERIFICATION   → Mobile entered, OTP not yet verified
MOBILE_VERIFIED               → OTP verified; account created
PROFILE_INCOMPLETE            → Stage 1 complete; Stage 2 below required threshold
PENDING_APPROVAL              → Profile satisfies requirements; awaiting approval
ACTIVE                        → Approval granted; full IAM-controlled access
REJECTED                      → Registration rejected (reason stored)
SUSPENDED                     → Temporary admin hold
BLOCKED                       → Hard block (fraud/security)
```

**Access policy:**
```
MOBILE_VERIFIED / PROFILE_INCOMPLETE:
  → May authenticate
  → May access: onboarding endpoints, profile completion, registration status
  → DENIED: any privileged/financial/business operation

PENDING_APPROVAL:
  → May authenticate (policy-dependent)
  → May access: status, limited onboarding
  → DENIED: all business operations

ACTIVE:
  → Normal operations — still require IAM tenant/role/permission checks
  → ACTIVE never bypasses AuthorizationResolver

SUSPENDED:
  → Authentication denied (fail closed)

BLOCKED:
  → Authentication denied (fail closed)

REJECTED:
  → Authentication denied (fail closed)
```

**Completion percentage:** Exists for UX/dashboard display only. NOT an authorization primitive.

**Eligibility boundary:** `ProfileCompletionPolicy` evaluates whether profile state satisfies prerequisites for a specific privileged action. Must check:
1. Required sections completed
2. Required identity verification satisfied
3. Required approval satisfied
4. Active tenant membership where applicable
5. IAM authorization via `AuthorizationResolver`

---

## 9. PA-06 — TENANT PROVISIONING DECISION

**DECISION: RESOLVED / FROZEN**

No tenant created during Stage 1. Platform user identity only.

**Tenant provisioning boundary:**
- Stage 1 creates `users` record only — no `tenants`, no `tenant_memberships` yet
- Individual/customer identities may exist without tenant ownership indefinitely
- `TenantProvisioningService` is the exclusive service for creating tenants; no inline tenant creation elsewhere

**`TenantProvisioningService` design requirements:**
- DB transaction-safe: `Tenant` creation + `TenantMembership` creation + initial `MembershipRole` assignment coordinated in one transaction
- Idempotent: if called twice for the same user+business, must not create a duplicate tenant
- Race-condition safe: uses DB unique constraints + advisory lock or check-then-act within transaction
- No orphan tenant: if membership or role assignment fails, tenant creation rolls back
- No automatic privilege escalation: tenant owner role assigned per explicit policy only
- Default DENY: new tenant membership starts as `pending` until explicitly activated per approval

**Trigger:** Business onboarding data completed + required validation + required approval boundary satisfied → `TenantProvisioningService::provision()` called.

---

## 10. PA-07 — PRE-USER MEDIA DECISION

**DECISION: RESOLVED / FROZEN**

Session-token pre-user identity media upload approved via **dedicated staging isolation** (Option A).

**Source evidence — why certified MediaAsset cannot be used directly:**
```php
// MediaUploadService::ingest() line 35:
$tenantId = ActiveTenantContext::get();  // THROWS if no tenant set

// MediaStorageRouter::generatePath() line 19:
public function generatePath(string $tenantId, string $ulid, ...): string

// media_assets migration line 13:
$table->char('tenant_id', 26)->index();  // NOT NULL
$table->foreign('tenant_id')->references('id')->on('tenants');  // FK CONSTRAINT
```

**Verdict:** The certified Wave 1B `MediaAsset` schema enforces `tenant_id NOT NULL` with FK to `tenants`. Forcing null tenant semantics into this table would violate certified invariants. Preferred approach: **dedicated `registration_identity_documents` staging table**.

**Architecture (Option A — Staging Isolation):**

```
registration_identity_documents
  id ULID PK
  registration_session_id char(26) FK → registration_sessions
  document_type varchar(30)  ← NID_FRONT / NID_BACK / PASSPORT / PROFILE_PHOTO
  storage_disk varchar(50)   ← always 'local' (private by default)
  storage_path varchar(500)  ← registration/staging/{session_ulid}/{doc_type}/{file_ulid}.ext
  mime_type varchar(100)
  extension varchar(10)
  size_bytes bigint unsigned
  checksum_sha256 char(64)
  security_status varchar(30) ← CLEAN / NOT_AVAILABLE / QUARANTINED
  uploaded_at timestamp
  claimed_at timestamp nullable   ← set when user account successfully created
  claimed_user_id bigint nullable ← set when claimed
  claimed_media_asset_id char(26) nullable ← rebind reference after user creation
  expires_at timestamp
  is_purged boolean default false
  timestamps
```

**Security properties satisfied:**
- Registration session token is opaque, hashed, short-TTL (15 min), revocable, purpose-bound, OTP-verified before any upload
- Cross-session access denied: document lookup always filtered by `registration_session_id`
- Enumeration resistant: ULID-based IDs, no sequential guessing
- Restricted media kinds: only `NID_FRONT`, `NID_BACK`, `PASSPORT`, `PROFILE_PHOTO` — no arbitrary general gateway access
- Storage namespace isolated: `registration/staging/{session_ulid}/...` — separate from `tenants/{tenant_ulid}/media/...`
- On successful user creation: `claimDocuments(User $user)` atomically rebinds documents to `media_assets` under `MediaUploadService` (now with valid tenant context or platform namespace per post-creation policy)
- Expired session cleanup: scheduled job purges staging files + marks `is_purged = true`
- No orphan indefinitely: cleanup policy enforced within 24h of session expiry

**Certified Wave 1B MediaAsset invariants: NOT WEAKENED.** The Wave 1B gateway is only used post-user-creation when a valid context exists.

---

## 11. USER ≠ ROLE ≠ TENANT INVARIANT

```
PLATFORM USER (users.id = BIGINT, enterprise_user_id = USR-YYYY-XXXXXXXX)
  ↓ (TenantMembership — when tenant exists)
TENANT MEMBERSHIP (tenant_id + user_id, status lifecycle)
  ↓ (MembershipRole — admin-assigned)
ROLE (authorization class; NOT identity)
  ↓ (AuthorizationGrant → Permission → AuthScope)
PERMISSION GRANTS (what the role can do within scope)

POSITION (PositionAssignment — organizational structure, NOT authorization)
  tied to membership, not to user directly

enterprise_user_id = platform identity only
  → NO organizational semantics
  → NO role embedded
  → NO tenant embedded
  → NO sequential exposure
```

---

## 12. FINAL STAGE 1 CONTRACT

```
STAGE 1 — PLATFORM IDENTITY CREATION

Step 1:  Client submits mobile (+ country code if applicable)
Step 2:  DestinationNormalizer normalizes to E.164/BD canonical
Step 3:  Uniqueness precheck: users.mobile_canonical (non-binding, DB constraint is final gate)
Step 4:  RegistrationSession created:
           id = ULID
           session_token = random_bytes(32) encoded as base64url
           session_token_hash = HMAC-SHA256(token, SESSION_SECRET)  ← stored; raw token returned once
           mobile_canonical = normalized mobile
           mobile_hash = hash_hmac('sha256', canonical, LOOKUP_SECRET)
           source = resolved from route/token/context (never client-supplied)
           status = AWAITING_OTP_VERIFICATION
           expires_at = now() + 15 minutes
Step 5:  OtpService::send(purpose: REGISTRATION_MOBILE, channel: SMS, tenantId: null, userId: null)
         → OTP record created with hashed code; SMS dispatched
Step 6:  Client submits session_id + otp_id + plaintext OTP code
         OtpService::verify(otpId, code, REGISTRATION_MOBILE)
         RegistrationSession status → OTP_VERIFIED
Step 7:  Client authenticates subsequent requests using session_token (opaque credential, short TTL)
Step 8:  Client uploads NID front, NID back, profile photo through restricted pre-user upload endpoint
         → RegistrationIdentityDocumentService::store()
         → OTP_VERIFIED state required; any other state → 422 Rejected
         → Files stored in registration/staging/{session_ulid}/...
         → registration_identity_documents record created
Step 9:  Client submits password (+ optional PIN)
         → password validated (confirmed, min complexity via Password::defaults())
         → PIN: classified as POLICY_DECISION_PENDING — not implemented in Wave 2 without explicit business semantics definition
Step 10: Client optionally submits email
         → email validated: format + uniqueness (nullable — skip if not provided)
Step 11: DB transaction opens:
         a. users.mobile_canonical DB UNIQUE check (final gate — fails on race)
         b. EnterpriseUserIdGenerator::generate() → USR-YYYY-XXXXXXXX (retry up to 5)
         c. User::create([
              mobile_canonical, mobile_verified_at=now(),
              email (nullable), password (hashed),
              enterprise_user_id, account_status=MOBILE_VERIFIED,
              registration_source=resolved source
            ])
         d. RegistrationIdentityDocumentService::claimDocuments(user) → rebinds staging docs
         e. RegistrationSession::markConsumed()
         f. Transaction commits
Step 12: Auth::login($user) → session regenerated
Step 13: account_status = MOBILE_VERIFIED (then PROFILE_INCOMPLETE if Stage 2 not complete)
Step 14: Client receives limited access — dashboard shows profile completion wizard
```

**PIN decision:** Security PIN is POLICY_DECISION_PENDING. The following is undefined without explicit business semantics:
- When is a PIN required vs password?
- Is PIN used for transaction confirmation, mobile login, or both?
- PIN is NOT implemented in Wave 2 unless PA provides explicit business definition.

---

## 13. FINAL STAGE 2 CONTRACT

```
STAGE 2 — PROFILE / BUSINESS ONBOARDING

Authentication required (MOBILE_VERIFIED or higher).
All endpoints are auth-gated; SUSPENDED/BLOCKED fail immediately.

Sections (not all required for every account type — policy-driven):

PERSONAL         → name_bangla, name_english, gender, DOB, nationality, religion, occupation
CONTACT          → secondary_mobile, whatsapp, email (if not set), social handles
ADDRESS          → PRESENT, PERMANENT address (BD division/district/upazila/union structure)
IDENTITY         → NID data review/confirm; legal identifiers; verification status
EMPLOYMENT       → Employer, designation, joining date, employment type
BUSINESS         → Business name, category, trade license, TIN, BIN (role-dependent)
BANKING          → Bank accounts (encrypted at rest via SensitiveDataCipherInterface)
MFS              → MFS providers (BKASH/NAGAD/ROCKET/UPAY)
DOCUMENTS        → Legal document attachments (now uses certified MediaGateway — user is created)
CONSENTS         → Digital consent records with document version + hash

Section requirements driven by:
  - account_status (where in lifecycle)
  - registration_source
  - requested business category
  - role assigned at activation

Profile section completion → ProfileCompletionPolicy evaluates prerequisites
→ If prerequisites met → account_status = PENDING_APPROVAL
→ Human/system approval → account_status = APPROVED
→ Tenant provisioned (if business account) → account_status = ACTIVE
→ Full IAM-controlled access

Individual customer accounts: may reach ACTIVE without tenant provisioning.
```

---

## 14. AUTHORITATIVE TABLE INVENTORY

**Discrepancy from earlier proposal resolved:** Earlier proposal claimed "13 additive tables" but did not list them all consistently. This section is authoritative.

### EXISTING FROZEN TABLES (must not be destructively altered)

| Table | Type | Frozen In | Notes |
|-------|------|-----------|-------|
| `users` | Platform | Wave 1 | BIGINT id; additive columns in Wave 2 |
| `password_reset_tokens` | Platform | Wave 1 | email-keyed; remains for email users |
| `sessions` | Platform | Wave 1 | unchanged |
| `cache` | Platform | Wave 1 | unchanged |
| `jobs` | Platform | Wave 1 | unchanged |
| `personal_access_tokens` | Platform | Wave 1 | Sanctum; unchanged |
| `currencies` | Platform | Wave 1 | unchanged |
| `outbox_events` | Platform | Wave 1 | `tenant_id NOT NULL` — platform flows deferred |
| `audit_logs` | Platform | Wave 1 | `tenant_id NOT NULL` — platform flows deferred |
| `tenants` | IAM | Wave 1A | ULID PK |
| `roles` | IAM | Wave 1A | |
| `permissions` | IAM | Wave 1A | |
| `authorization_grants` | IAM | Wave 1A | |
| `tenant_memberships` | IAM | Wave 1A | `user_id BIGINT FK → users` |
| `membership_roles` | IAM | Wave 1A | |
| `positions` | IAM | Wave 1A | |
| `position_assignments` | IAM | Wave 1A | |
| `media_assets` | Media | Wave 1B | `tenant_id NOT NULL` FK; cannot hold pre-user media |
| `otp_records` | Comm | Wave 1C | nullable `tenant_id`; nullable `user_id` — safe for pre-user OTP |

### ADDITIVE COLUMNS ON `users` (Wave 2 migration — additive only)

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `mobile_canonical` | varchar(20) | UNIQUE, nullable (→ required for new Wave 2 users at app layer) | Primary login identity; E.164 normalized |
| `mobile_verified_at` | timestamp | nullable | OTP verification timestamp |
| `enterprise_user_id` | varchar(20) | UNIQUE, nullable | `USR-YYYY-XXXXXXXX` — platform identity |
| `account_status` | varchar(30) | NOT NULL default 'MOBILE_VERIFIED' | See state machine |
| `registration_source` | varchar(30) | nullable | server-resolved |
| `two_factor_enabled` | boolean | NOT NULL default false | 2FA flag |

Note: `users.email` column (existing) → new additive migration changes it to `nullable()`.

### NEW ADDITIVE TABLES (Wave 2 — exact count: 10 new tables + 1 staging table = 11)

#### 1. `registration_sessions`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `session_token_hash` | char(64) | UNIQUE NOT NULL | HMAC-SHA256 of session token |
| `mobile_canonical` | varchar(20) | NOT NULL, INDEX | Normalized mobile |
| `mobile_hash` | char(64) | NOT NULL, INDEX | HMAC-SHA256 of canonical mobile |
| `otp_record_id` | char(26) | nullable, FK → otp_records | |
| `source` | varchar(30) | NOT NULL | RegistrationSource |
| `bound_tenant_id` | char(26) | nullable | Invitation-bound tenant only |
| `invitation_token_hash` | char(64) | nullable | HMAC-SHA256 of invite token |
| `status` | varchar(30) | NOT NULL | AWAITING_OTP/OTP_VERIFIED/SECURITY_SUBMITTED/COMPLETED/EXPIRED/CANCELLED |
| `attempt_count` | tinyint unsigned | NOT NULL default 0 | Replay protection |
| `otp_verified_at` | timestamp | nullable | |
| `consumed_at` | timestamp | nullable | Set when user created |
| `ip_address` | varchar(45) | nullable | |
| `user_agent` | varchar(500) | nullable | |
| `expires_at` | timestamp | NOT NULL, INDEX | |
| `timestamps` | | | |
- **Scope:** Platform (no tenant FK)
- **Sensitive:** `mobile_hash` searchable; raw mobile stored in `mobile_canonical` (masked in logs)
- **Retention:** Expired sessions purged after 24h by cleanup job

#### 2. `registration_identity_documents`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `registration_session_id` | char(26) | NOT NULL, FK, INDEX | Staging owner |
| `document_type` | varchar(30) | NOT NULL | NID_FRONT / NID_BACK / PASSPORT / PROFILE_PHOTO |
| `storage_disk` | varchar(50) | NOT NULL | always 'local' |
| `storage_path` | varchar(500) | UNIQUE NOT NULL | `registration/staging/{sess_ulid}/{type}/{file_ulid}.ext` |
| `mime_type` | varchar(100) | NOT NULL | |
| `extension` | varchar(10) | NOT NULL | |
| `size_bytes` | bigint unsigned | NOT NULL | |
| `checksum_sha256` | char(64) | nullable, INDEX | |
| `security_status` | varchar(30) | NOT NULL | CLEAN / NOT_AVAILABLE / QUARANTINED |
| `uploaded_at` | timestamp | NOT NULL | |
| `claimed_at` | timestamp | nullable | Set on user creation |
| `claimed_user_id` | bigint | nullable, FK → users | Set on user creation |
| `claimed_media_asset_id` | char(26) | nullable | Reference to MediaAsset after rebind |
| `expires_at` | timestamp | NOT NULL, INDEX | |
| `is_purged` | boolean | NOT NULL default false | Cleanup flag |
| `timestamps` | | | |
- **Scope:** Platform staging (no tenant FK)
- **Lifecycle:** Created during Stage 1 upload → claimed on user creation → purged on session expiry or 24h after session expiry if not claimed

#### 3. `user_identity_verifications`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `user_id` | bigint | UNIQUE, NOT NULL, FK → users | One-to-one |
| `nid_number_hmac` | char(64) | UNIQUE nullable, INDEX | HMAC-SHA256 lookup fingerprint |
| `nid_number_encrypted` | text | nullable | Encrypted at rest |
| `name_bangla` | varchar(200) | nullable | OCR-derived or user-entered |
| `name_english` | varchar(200) | nullable | OCR-derived or user-entered |
| `father_name` | varchar(200) | nullable | |
| `mother_name` | varchar(200) | nullable | |
| `date_of_birth` | date | nullable | |
| `blood_group` | varchar(5) | nullable | |
| `place_of_birth` | varchar(200) | nullable | |
| `nid_photo_front_id` | char(26) | nullable, FK → media_assets | After claim |
| `nid_photo_back_id` | char(26) | nullable, FK → media_assets | After claim |
| `nid_verification_status` | varchar(30) | NOT NULL default 'UNVERIFIED' | UNVERIFIED/PENDING/VERIFIED/REJECTED/EXPIRED |
| `nid_verified_at` | timestamp | nullable | |
| `porichoy_reference` | varchar(100) | nullable | Govt verification ref |
| `porichoy_status` | varchar(30) | nullable | NOT_CONFIGURED/PENDING/VERIFIED/FAILED/MANUAL_REVIEW_REQUIRED |
| `verification_source` | varchar(30) | nullable | MANUAL_ENTRY/OCR_UNVERIFIED/PORICHOY |
| `timestamps` | | | |
- **Scope:** Platform (user-owned, no tenant FK)
- **Sensitive:** `nid_number_hmac` is HMAC not raw hash; `nid_number_encrypted` never logged; display masked as `****XXXX`

#### 4. `user_profiles`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `user_id` | bigint | UNIQUE NOT NULL FK → users | One-to-one |
| `profile_photo_id` | char(26) | nullable, FK → media_assets | After Stage 1 claim |
| `display_name` | varchar(200) | nullable | |
| `gender` | varchar(20) | nullable | |
| `religion` | varchar(30) | nullable | |
| `nationality` | varchar(50) | nullable | |
| `marital_status` | varchar(20) | nullable | |
| `occupation` | varchar(100) | nullable | |
| `education_level` | varchar(100) | nullable | |
| `profession` | varchar(100) | nullable | |
| `signature_media_id` | char(26) | nullable, FK → media_assets | |
| `emergency_contact_name` | varchar(200) | nullable | |
| `emergency_contact_relation` | varchar(50) | nullable | |
| `emergency_contact_mobile` | varchar(20) | nullable | |
| `timestamps` | | | |
- **Scope:** Platform (user-owned)

#### 5. `user_contact_details`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `user_id` | bigint | UNIQUE NOT NULL FK → users | One-to-one |
| `secondary_mobile` | varchar(20) | nullable | |
| `whatsapp` | varchar(20) | nullable | |
| `email` | varchar(255) | nullable | Contact email (may differ from login email) |
| `alternative_email` | varchar(255) | nullable | |
| `website` | varchar(255) | nullable | |
| `facebook` | varchar(255) | nullable | |
| `linkedin` | varchar(255) | nullable | |
| `telegram` | varchar(20) | nullable | |
| `imo` | varchar(20) | nullable | |
| `timestamps` | | | |
- **Note:** `users.email` = login/identity email. `user_contact_details.email` = contact email (no uniqueness constraint, not used for auth)

#### 6. `user_addresses`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `user_id` | bigint | NOT NULL FK → users, INDEX | |
| `type` | varchar(20) | NOT NULL | PRESENT/PERMANENT/OFFICE/SHIPPING/BILLING |
| `division` | varchar(100) | nullable | |
| `district` | varchar(100) | nullable | |
| `upazila` | varchar(100) | nullable | |
| `union_ward` | varchar(100) | nullable | |
| `village_road` | varchar(255) | nullable | |
| `post_code` | varchar(10) | nullable | |
| `latitude` | decimal(10,8) | nullable | Geocoded — explicit consent required |
| `longitude` | decimal(11,8) | nullable | |
| `is_primary` | boolean | NOT NULL default false | |
| `timestamps` | | | |
- UNIQUE: `(user_id, type)` — one of each type per user

#### 7. `user_bank_accounts`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `user_id` | bigint | NOT NULL FK → users, INDEX | |
| `bank_name` | varchar(200) | nullable | |
| `branch_name` | varchar(200) | nullable | |
| `account_name` | varchar(200) | nullable | |
| `account_number_encrypted` | text | nullable | Encrypted via `SensitiveDataCipherInterface` |
| `account_number_hmac` | char(64) | nullable, INDEX | HMAC-SHA256 for deduplication |
| `routing_number` | varchar(20) | nullable | |
| `swift_code` | varchar(11) | nullable | |
| `is_verified` | boolean | NOT NULL default false | |
| `is_primary` | boolean | NOT NULL default false | |
| `timestamps` | | | |
- **Sensitive:** `account_number_encrypted` never logged; `account_number_hmac` is keyed

#### 8. `user_mfs_accounts`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `user_id` | bigint | NOT NULL FK → users, INDEX | |
| `provider` | varchar(30) | NOT NULL | BKASH/NAGAD/ROCKET/UPAY/CELLFIN/OTHER |
| `mobile_canonical` | varchar(20) | NOT NULL | Normalized MFS mobile |
| `is_primary_payout` | boolean | NOT NULL default false | |
| `timestamps` | | | |
- UNIQUE: `(user_id, provider, mobile_canonical)`

#### 9. `user_legal_identifiers`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `user_id` | bigint | NOT NULL FK → users, INDEX | |
| `type` | varchar(30) | NOT NULL | PASSPORT/DRIVING_LICENSE/TIN/TRADE_LICENSE/BIRTH_CERT/COMPANY_REG/VAT/IMPORT_PERMIT/EXPORT_PERMIT |
| `identifier_hmac` | char(64) | nullable, INDEX | HMAC-SHA256 |
| `identifier_encrypted` | text | nullable | Encrypted at rest |
| `issued_by` | varchar(100) | nullable | |
| `issued_at` | date | nullable | |
| `expires_at` | date | nullable | |
| `media_asset_id` | char(26) | nullable, FK → media_assets | |
| `verification_status` | varchar(30) | NOT NULL default 'UNVERIFIED' | |
| `timestamps` | | | |

#### 10. `business_profiles`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `user_id` | bigint | NOT NULL FK → users | Owner/primary contact |
| `tenant_id` | char(26) | nullable, FK → tenants, INDEX | Set after TenantProvisioningService runs |
| `business_name` | varchar(300) | NOT NULL | |
| `business_name_bangla` | varchar(300) | nullable | |
| `business_category_code` | varchar(50) | NOT NULL | Config-driven category |
| `business_type_code` | varchar(50) | nullable | |
| `trade_license_number_encrypted` | text | nullable | |
| `trade_license_number_hmac` | char(64) | nullable, INDEX | |
| `tin_number_encrypted` | text | nullable | |
| `tin_number_hmac` | char(64) | nullable, INDEX | |
| `bin_number_hmac` | char(64) | nullable, INDEX | |
| `company_reg_number_hmac` | char(64) | nullable, INDEX | |
| `established_at` | date | nullable | |
| `employee_count_range` | varchar(30) | nullable | |
| `annual_revenue_range` | varchar(50) | nullable | |
| `verification_status` | varchar(30) | NOT NULL default 'UNVERIFIED' | |
| `metadata` | json | nullable | |
| `timestamps` | | | |

#### 11. `user_consents`
| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | char(26) | PK ULID | |
| `user_id` | bigint | NOT NULL FK → users, INDEX | |
| `document_type` | varchar(50) | NOT NULL | PRIVACY_POLICY/TERMS/EMPLOYMENT_CONTRACT/DEALER_AGREEMENT/ENTREPRENEUR_AGREEMENT |
| `document_version` | varchar(20) | NOT NULL | Semantic version |
| `document_hash` | char(64) | NOT NULL | SHA-256 of the document at time of consent |
| `accepted_at` | timestamp | NOT NULL | |
| `ip_address` | varchar(45) | nullable | |
| `user_agent` | varchar(255) | nullable | |
| `correlation_id` | varchar(36) | nullable | |
| `timestamps` | | | |
- UNIQUE: `(user_id, document_type, document_version)` — one consent per version

**Total new tables: 11** (not 13 as stated in earlier proposal — earlier proposal counted imprecisely)

---

## 15. SENSITIVE DATA ARCHITECTURE

| Data | Storage | Lookup | Display |
|------|---------|--------|---------|
| NID number | `nid_number_encrypted` (AES-256-CBC via SensitiveDataCipherInterface) | `nid_number_hmac` (HMAC-SHA256 with SENSITIVE_LOOKUP_SECRET) | `****XXXX` (last 4 shown) |
| Bank account number | `account_number_encrypted` | `account_number_hmac` | masked |
| Trade license number | `trade_license_number_encrypted` | `trade_license_number_hmac` | masked |
| TIN/BIN | `tin_number_encrypted` / `bin_number_encrypted` | HMAC fields | masked |
| Mobile (canonical) | plaintext in `mobile_canonical` (moderate entropy) | `mobile_hash` in registration_sessions | masked in logs |
| Email | plaintext in `users.email` | direct unique index | masked in logs |
| Password | `Hash::make()` (bcrypt) | `Hash::check()` | never exposed |
| Session token | raw returned once at creation | `session_token_hash` (HMAC) stored | never re-exposed |

**Key inventory:**
- `APP_KEY` — Laravel encryption key (Crypt facade)
- `SENSITIVE_LOOKUP_SECRET` — dedicated env var for HMAC lookup hashing; never APP_KEY

**Log redaction:** All log statements that touch mobile, email, NID, account numbers must use masking helpers:
- `DestinationNormalizer::maskMobile()` (already exists in Wave 1C)
- New: `SensitiveDataLogger::redact()` or log context exclusion

---

## 16. REGISTRATION SESSION SECURITY

| Property | Implementation |
|----------|---------------|
| Token generation | `random_bytes(32)` → base64url encoded → returned once, never re-exposed |
| Token storage | `session_token_hash = hash_hmac('sha256', rawToken, SESSION_HMAC_SECRET)` |
| Session TTL | 15 minutes (`expires_at`) |
| Purpose binding | `source` column; different sources lead to different downstream logic |
| State binding | Status column; state transitions validated before each operation |
| OTP required before upload | `status = OTP_VERIFIED` required or 422 returned |
| Rate limiting | Registration initiation: 3 per mobile per 10 min; per IP: 10 per hour |
| Replay protection | `attempt_count` incremented; consumed session cannot re-initiate |
| Enumeration resistance | ULID IDs; session lookup only via HMAC token hash |
| Session revocation | Status set to CANCELLED; expired sessions purged |

---

## 17. PRE-USER MEDIA ARCHITECTURE

See section 10 (PA-07) for full design. Summary:

- `registration_identity_documents` staging table — isolated from certified `media_assets`
- Upload endpoint: `POST /api/v1/registration/identity-documents`
- Auth: session token (validated via `session_token_hash` HMAC lookup)
- Required state: `OTP_VERIFIED`
- Storage path: `registration/staging/{session_ulid}/{doc_type}/{file_ulid}.{ext}`
- Allowed kinds: `NID_FRONT`, `NID_BACK`, `PASSPORT`, `PROFILE_PHOTO`
- Security scan: applied (uses existing `MalwareScannerInterface`)
- GD image processing: applied for IMAGE kind (uses existing `ImageOptimizerInterface`)
- File size limits enforced via existing `MediaValidationPolicy` patterns
- On user creation: `RegistrationIdentityDocumentService::claimDocuments(User)` rebinds to `media_assets`
- Expired/unclaimed: purged by scheduled cleanup job after 24h past `expires_at`

**Certified Wave 1B invariants: NOT WEAKENED.**

---

## 18. OCR STATUS

**CONFIRMED: NOT PRESENT IN REPOSITORY.**

Wave 2 provides interface boundary only:

```php
interface IdentityDocumentExtractionInterface
{
    public function extract(string $filePath, string $documentType): IdentityDocumentExtractionResult;
    public function isConfigured(): bool;
}
```

`IdentityDocumentExtractionResult` truthful states:
```
NOT_CONFIGURED, PENDING, EXTRACTED_UNVERIFIED, FAILED, MANUAL_ENTRY_REQUIRED
```

`NullIdentityDocumentExtractor` (always returns `NOT_CONFIGURED`) is the initial implementation.
No fake OCR. No fabricated extraction results.

---

## 19. PORICHOY STATUS

**CONFIRMED: NOT PRESENT IN REPOSITORY. CONFIGURATION/CONTRACT PENDING.**

Wave 2 provides interface boundary only:

```php
interface IdentityVerificationProviderInterface
{
    public function verify(string $nidNumber, string $nameEnglish, \DateTimeImmutable $dob): IdentityVerificationResult;
    public function isConfigured(): bool;
}
```

`IdentityVerificationResult` truthful states:
```
NOT_CONFIGURED, PENDING, VERIFIED, FAILED, MANUAL_REVIEW_REQUIRED
```

`NullIdentityVerificationProvider` (always returns `NOT_CONFIGURED`) is the initial implementation.
No fake Porichoy. `nid_verification_status = 'UNVERIFIED'` for all manual entries.

---

## 20. AUTHENTICATION TRANSITION PLAN

**Source confirmed (LoginRequest — FROZEN):**
```php
return ['email' => ['required', 'string', 'email'], 'password' => ['required', 'string']];
Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))
```

**Design (NOT yet implemented):**

Strategy: single `credential` field that accepts either mobile or email. Server resolves type.

```
POST /login
  { credential: "01700000000" | "user@example.com", password: "..." }
```

Resolution logic in `MobileAwareLoginRequest`:
1. If `credential` matches mobile pattern (BD: 01[3-9]XXXXXXXX or +880...) → normalize → attempt by `mobile_canonical`
2. Else → validate as email → attempt by `email`
3. Rate limiting uses same throttle key pattern: `hash(credential|IP)`
4. Account state check after successful credential match: SUSPENDED/BLOCKED fail; MOBILE_VERIFIED/PROFILE_INCOMPLETE allow limited access; ACTIVE allows full access

**Why single field:**
- Prevents user confusion ("which field am I supposed to use?")
- Avoids exposing whether a user registered with mobile vs email
- Reduces client form complexity
- Consistent with WhatsApp/Bkash/Nagad UX patterns (single mobile/email field)

**Backward compatibility:**
- Email + password login for existing (Wave 1 era) users: continues to work
- `Auth::attempt(['email' => $email, 'password' => $pwd])` still valid path
- Mobile + password login for Wave 2 users: uses `Auth::attempt(['mobile_canonical' => $normalized, 'password' => $pwd])`
- `User` model must declare `mobile_canonical` as a valid auth credential via `username()` or custom user provider

---

## 21. IAM BOUNDARY

**Invariants:**
- Registration input NEVER directly grants: Super Admin, Tenant Admin, privileged role, AuthorizationGrant, arbitrary tenant membership, approval state
- `registration_source` is server-resolved — not client-supplied
- Requested business category = classification request, NOT granted authorization
- Default role for new accounts: determined by `source` and `approved` by policy actor, not by self-declaration
- `AuthorizationGrantService::grant()` is the ONLY path to privileged role assignment
- `AuthorizationResolver::check()` is the ONLY path to authorization verification

**Registration source → default classification mapping (not a grant, just a requested classification):**
```
PUBLIC       → requested classification: CUSTOMER
INVITATION   → bound by invitation policy + admin confirmation
INTERNAL_HR  → HR admin creates and assigns; user cannot self-select
DEALER       → requested classification: DEALER; requires document verification + approval
ENTREPRENEUR → requested classification: ENTREPRENEUR; requires KYC + approval
ADMIN_CREATED → classification set by admin; user completes profile
```

---

## 22. TENANT PROVISIONING BOUNDARY

`TenantProvisioningService` is the ONLY service that creates tenants.

**Trigger contract:**
```
Prerequisites to call TenantProvisioningService::provision():
  1. user.account_status = PENDING_APPROVAL or higher
  2. BusinessProfile exists and has required fields for the category
  3. Required legal documents verified (or waived by policy)
  4. Approval decision made by authorized actor
  5. No existing tenant for this user+business combination (idempotency check)

Actions (all within one DB transaction):
  1. Tenant::create([name, status=active])
  2. TenantMembership::create([tenant_id, user_id, status=active])
  3. MembershipRoleService::assign(membershipId, ownerRoleId) — via policy-defined owner role
  4. BusinessProfile::update([tenant_id=new_tenant_id])
  5. User::update([account_status=ACTIVE])
  6. Rollback all on any failure
```

---

## 23. ACCOUNT STATE MACHINE

```
[Initial] → PENDING_MOBILE_VERIFICATION
  ↓ (OTP verified)
PENDING_MOBILE_VERIFICATION → MOBILE_VERIFIED

MOBILE_VERIFIED → PROFILE_INCOMPLETE (when Stage 2 not yet started or below threshold)
PROFILE_INCOMPLETE → PENDING_APPROVAL (prerequisites met; user submits for review)
PENDING_APPROVAL → ACTIVE (approval granted; tenant provisioned if applicable)
PENDING_APPROVAL → REJECTED (approval denied; reason stored)

ACTIVE → SUSPENDED (admin action)
SUSPENDED → ACTIVE (admin reinstates)
ACTIVE → BLOCKED (fraud/security event)
BLOCKED → ACTIVE (requires exceptional review; not automatic)
Any state → REJECTED → terminal (no auto-recovery)

Note: MOBILE_VERIFIED and PROFILE_INCOMPLETE both allow limited authentication.
Authentication under these states only permits onboarding endpoints.
All privileged operations require ACTIVE + IAM authorization.
```

---

## 24. API CONTRACT (Proposed — NOT implemented)

### Registration Endpoints (unauthenticated, CSRF protected)

```
POST /api/v1/registration/initiate
  Auth: none (unauthenticated)
  State required: none
  Rate limit: 3/10min per mobile hash; 10/1hr per IP
  Body: { country_code?: "+880", mobile: "01700000000" }
  Response: { session_id: ULID, otp_id: ULID, expires_at, masked_mobile }
  Anti-enumeration: identical 200 response shape even if mobile already exists (real error surfaces at Step 11 DB constraint)
  Idempotent: NO — each call creates a new session

POST /api/v1/registration/otp/verify
  Auth: none
  State required: session status = AWAITING_OTP_VERIFICATION
  Body: { session_id, otp_id, code }
  Response: { session_token: "opaque-base64url", session_id, status: "OTP_VERIFIED", expires_at }
  Note: session_token is the opaque credential for subsequent Stage 1 requests

POST /api/v1/registration/otp/resend
  Auth: session_token
  State required: AWAITING_OTP_VERIFICATION
  Rate limit: resend cooldown (60s) enforced by Wave 1C
  Body: { session_id }
  Response: { otp_id, masked_mobile, retry_after? }

POST /api/v1/registration/identity-documents
  Auth: session_token (validated via HMAC lookup)
  State required: OTP_VERIFIED
  Rate limit: 10 uploads per session
  Body: multipart/form-data: file, document_type (NID_FRONT/NID_BACK/PASSPORT/PROFILE_PHOTO)
  Response: { document_id, document_type, uploaded_at }

POST /api/v1/registration/account
  Auth: session_token
  State required: OTP_VERIFIED
  Body: { password, password_confirmation, email?: optional }
  Response: { user_id, enterprise_user_id, account_status } + session cookie (logged in)
  Creates: User record; claims documents; marks session consumed

GET /api/v1/registration/status
  Auth: session_token OR authenticated user
  Response: { session_status, account_status?, progress }
```

### Profile Endpoints (authenticated, account_status ≥ MOBILE_VERIFIED)

```
GET  /api/v1/profile
  Returns: all profile sections summary + completion status per section

GET  /api/v1/profile/requirements
  Returns: { required_sections, optional_sections } driven by registration_source + business_category

POST /api/v1/profile/personal
POST /api/v1/profile/contact
POST /api/v1/profile/address
POST /api/v1/profile/identity          (NID data review/confirm)
POST /api/v1/profile/employment
POST /api/v1/profile/business
POST /api/v1/profile/banking
POST /api/v1/profile/mfs
POST /api/v1/profile/documents         (legal document upload — now uses Wave 1B MediaGateway)
POST /api/v1/profile/consents

GET  /api/v1/profile/completion
  Returns: { score: 72, missing_required: [...], missing_optional: [...], status }

POST /api/v1/profile/submit-for-approval
  Preconditions: required sections complete; required identity submitted
  State transition: PROFILE_INCOMPLETE → PENDING_APPROVAL
```

---

## 25. THREAT MODEL

See `docs/security/WAVE_02_REGISTRATION_THREAT_MODEL.md` (produced separately).

Summary of key mitigations:

| Threat | Mitigation |
|--------|-----------|
| OTP replay | `OtpService::verify()` uses `lockForUpdate` + marks CONSUMED atomically |
| OTP brute force | Wave 1C: max_attempts (5) → LOCKED; Wave 1C rate limits |
| Registration session theft | Short TTL (15min); HMAC token hash stored (not raw); HTTPS only |
| Session fixation | New session token on each registration initiation |
| Registration enumeration | Identical 200 response shape for existing/new mobile during initiation |
| Mobile duplicate race | DB UNIQUE constraint on `users.mobile_canonical` is final gate |
| Enterprise ID collision | Retry loop + DB UNIQUE; max 5 retries then throw |
| NID duplicate race | DB UNIQUE on `user_identity_verifications.nid_number_hmac` |
| NID hash dictionary attack | HMAC-SHA256 with server-held `SENSITIVE_LOOKUP_SECRET`; raw SHA-256 rejected |
| Media cross-session access | Docs always looked up filtered by `registration_session_id` |
| Pre-user upload abuse | Session token required; OTP_VERIFIED state required; rate limited; media kind whitelist |
| Decompression/image attacks | Existing `ImageOptimizerInterface` + GD processing applied |
| Mass assignment | Explicit `$fillable`; server-computed fields excluded |
| Privilege injection | `registration_source` server-resolved; `role_id` never client-supplied |
| Tenant injection | `tenant_id` set server-side from authenticated context only |
| Role injection | All role assignment via `AuthorizationGrantService`; never from registration payload |
| Approval bypass | `account_status` transitions validated server-side; no client-supplied status |
| Account-state bypass | Middleware checks `account_status` before routing to privileged endpoints |
| Legacy /register bypass | `POST /register` returns 410 or is repointed; legacy controller disabled |
| Login enumeration | Generic "credentials do not match" error regardless of whether mobile/email exists |
| Sensitive logs | Mobile masked; NID never logged; account numbers never logged |
| Backup exposure | Sensitive fields encrypted at rest; key stored separately from DB backup |
| Abandoned registration PII | Expired sessions purged after 24h; staging files purged on cleanup |
| Orphan media | Compensating action in `RegistrationIdentityDocumentService::claimDocuments()` rollback |
| Transaction partial failure | DB transactions wrap all multi-step operations; rollback on failure |

---

## 26. MIGRATION PLAN

All migrations ADDITIVE ONLY. Never `migrate:fresh` against `raisa_erp`.

```
Wave 2A:
2026_08_XX_000001_add_registration_columns_to_users_table.php
  → make email nullable (preserving unique index)
  → add mobile_canonical UNIQUE nullable
  → add mobile_verified_at
  → add enterprise_user_id UNIQUE nullable
  → add account_status NOT NULL default 'MOBILE_VERIFIED'
  → add registration_source nullable
  → add two_factor_enabled NOT NULL default false

2026_08_XX_000002_create_registration_sessions_table.php
2026_08_XX_000003_create_registration_identity_documents_table.php

Wave 2B:
2026_08_XX_000004_create_user_identity_verifications_table.php
2026_08_XX_000005_create_user_profiles_table.php
2026_08_XX_000006_create_user_contact_details_table.php
2026_08_XX_000007_create_user_addresses_table.php

Wave 2D:
2026_08_XX_000008_create_user_bank_accounts_table.php
2026_08_XX_000009_create_user_mfs_accounts_table.php
2026_08_XX_000010_create_user_legal_identifiers_table.php
2026_08_XX_000011_create_business_profiles_table.php
2026_08_XX_000012_create_user_consents_table.php
```

**Safety enforcement per migration:**
- DatabaseSafetyPolicy checked before each migration run
- Local test target: `raisa_erp_wave1b_test` (MariaDB)
- Remote CI: MySQL 8 via GitHub Actions
- `down()` methods implemented where safely reversible

---

## 27. TEST MATRIX

See detailed test matrix in `ADR_WAVE_02_IDENTITY_REGISTRATION.md`.

Summary coverage groups:

| Group | Key Scenarios | Priority |
|-------|--------------|----------|
| Registration session | valid lifecycle, expired, invalid token, consumed, replay, cross-session | P0 |
| OTP integration | Wave 1C reuse, purpose isolation, brute force, resend cooldown | P0 |
| User identity | mobile normalization, duplicate mobile (race), optional email, duplicate non-null email, enterprise ID uniqueness | P0 |
| Sensitive identity | encrypted at rest, HMAC fingerprint, duplicate NID, no plaintext in logs | P0 |
| Pre-user media | OTP required before upload, allowed kinds only, cross-session denial, claim/rebind, expired cleanup | P0 |
| Account state | MOBILE_VERIFIED limited access, PENDING_APPROVAL gate, ACTIVE gate, SUSPENDED denied, BLOCKED denied | P0 |
| IAM | role injection rejected, tenant injection rejected, privilege escalation rejected, source injection rejected | P0 |
| Legacy auth | old email login preserved, new mobile login, POST /register cannot bypass Wave 2 | P0 |
| Database | MariaDB local, MySQL 8 CI, migration rollback | P0 |
| Regression | Wave 1C unmodified, Wave 1B unmodified, Wave 1B.3 unmodified, Wave 1A unmodified, full backend, frontend build, lint | P0 |

---

## 28. MARIADB COMPATIBILITY PLAN

- All migrations use Laravel Blueprint methods (no raw DDL) → MariaDB compatible
- `->unique()` on nullable column: supported in MariaDB (NULL values excluded from uniqueness)
- `char(26)` ULID columns: compatible
- HMAC-SHA256 fingerprints stored as `char(64)`: compatible
- Encrypted text fields (`text` type): compatible
- `json` type: compatible in MariaDB 10.2+
- Local test environment: MariaDB (as confirmed from Wave 1B.3 certification)
- Migration rollback: `down()` methods implemented using `Schema::dropIfExists()` / `Schema::table()` reversals

---

## 29. MYSQL 8 CI PLAN

- GitHub Actions CI pipeline (inherited from Wave 1B) extended to include Wave 2 migrations
- MySQL 8 specific: verify `UNIQUE` on nullable `email` column behavior matches MariaDB
- MySQL 8 `json` type: fully supported
- `char(64)` HMAC columns: compatible
- JSON strict mode: already handled by existing migration patterns
- CI gate: must pass all Wave 2 migrations + tests before any Wave 2 commit is tagged

---

## 30. WAVE 1C REGRESSION BOUNDARY

The following Wave 1C components are IMMUTABLE in Wave 2 except for strictly backward-compatible additions:

- `OtpService` — used as-is; no modification required
- `OtpRecord` model — no schema changes
- `OtpPurpose` enum — `REGISTRATION_MOBILE` and `REGISTRATION_EMAIL` already present (confirmed in source)
- `DestinationNormalizer` — used as-is
- `CommunicationManager` — used as-is
- `otp_records` migration — no changes

**Regression test:** All Wave 1C tests must pass unchanged after Wave 2 implementation.

---

## 31. WAVE 1B REGRESSION BOUNDARY

The following Wave 1B components are IMMUTABLE in Wave 2:

- `MediaUploadService` — used as-is for post-user Stage 2 document uploads
- `MediaAsset` model — no schema changes
- `MediaAccessService` — used as-is
- `MediaStorageRouter` — used as-is
- `media_assets` migration — no changes

**New `registration_identity_documents` staging table:** completely separate; does NOT modify Wave 1B schema or logic.

---

## 32. DATABASE SAFETY BOUNDARY

- `raisa_erp` = PROTECTED (confirmed in `DatabaseSafetyPolicy`)
- All Wave 2 development migrations against `raisa_erp_wave1b_test` only
- `DB_ALLOWED_TEST_DATABASES` may be extended to include `raisa_erp_wave2_test` if needed
- `migrate:fresh` NEVER against `raisa_erp`
- All migration runs pass through `DatabaseSafetyPolicy::isDestructiveCommandAllowed()` check

---

## 33. PROPOSED WAVE 2A–2H SEQUENCE

```
Wave 2A — Registration Session & Identity Schema Foundation
  Parent: 075ed0a
  Scope: users additive migration, registration_sessions, registration_identity_documents
  Migrations: 3 migrations
  Code: EnterpriseUserIdGenerator, RegistrationSession model, SensitiveDataCipherInterface,
        SensitiveLookupHasherInterface, LaravelSensitiveDataCipher, HmacSha256LookupHasher
  Tests: session lifecycle, mobile normalization, enterprise ID uniqueness
  Gate: MariaDB + MySQL 8 CI pass

Wave 2B — Mobile-First Account Creation & Authentication Transition
  Parent: Wave 2A SHA
  Scope: Stage 1 API (initiate, OTP send/verify, account create), MobileAwareLoginRequest
  Code: RegistrationController (Stage 1), RegistrationSessionService, MobileAwareLoginRequest
  Tests: full Stage 1 flow, mobile duplicate race, legacy email login preserved,
         POST /register deprecation
  Gate: all P0 registration tests pass

Wave 2C — Pre-User Identity Media Boundary
  Parent: Wave 2B SHA
  Scope: RegistrationIdentityDocumentService, upload endpoint, staging storage,
         claim/rebind on user creation
  Code: RegistrationIdentityDocumentService, IdentityDocumentUploadController
  Tests: OTP required, allowed kinds, cross-session denial, claim, expired cleanup
  Gate: Wave 1B regression tests unmodified

Wave 2D — Progressive Profile Engine
  Parent: Wave 2C SHA
  Scope: user_profiles, user_contact_details, user_addresses, user_bank_accounts,
         user_mfs_accounts, user_legal_identifiers, user_consents migrations + services
  Code: ProfileService (section-level), ProfileSectionController, ProfileCompletionPolicy
  Tests: section save, completion logic, sensitive data encrypted, HMAC lookup correct
  Gate: all profile section P1 tests pass

Wave 2E — Identity Provider Interfaces & Manual Review
  Parent: Wave 2D SHA
  Scope: user_identity_verifications, IdentityDocumentExtractionInterface,
        IdentityVerificationProviderInterface, NullIdentityDocumentExtractor,
        NullIdentityVerificationProvider
  Code: provider interfaces + null implementations + verification status transitions
  Tests: null providers return NOT_CONFIGURED, manual entry not falsely verified
  Gate: no fake OCR/Porichoy; all provider states correct

Wave 2F — Business Profile & Tenant Provisioning Boundary
  Parent: Wave 2E SHA
  Scope: business_profiles migration, BusinessProfileService,
         business category engine (config-driven), TenantProvisioningService
  Code: TenantProvisioningService, BusinessCategoryConfig, BusinessProfileService
  Tests: tenant provisioning atomic, idempotent, no orphan tenant, no auto-privilege escalation
  Gate: IAM invariants verified

Wave 2G — Approval / Activation / IAM Integration
  Parent: Wave 2F SHA
  Scope: account_status state machine, PENDING_APPROVAL → ACTIVE transition,
         REJECTED with reason, middleware for account-state enforcement
  Code: AccountStateMiddleware, ApprovalService, account status guards
  Tests: approval gate, activation IAM, rejected terminal, suspended fail-closed
  Gate: all P0 account state tests pass

Wave 2H — Security Hardening & Full Certification
  Parent: Wave 2G SHA
  Scope: full threat model verification, concurrency tests, cleanup job (expired sessions/docs),
         log redaction audit, sensitive data audit
  Tests: all P0 security tests, MariaDB + MySQL 8 CI, full regression suite
  Gate: PA Final Lock Review → Wave 2 commit → push

Each sub-wave has:
  - Frozen parent SHA
  - Explicit scope boundary
  - Migrations
  - Application code
  - Security invariants
  - Test certification
  - Deferred items documented
```

---

## 34. DOCUMENTATION PRODUCED

| Document | Path | Status |
|----------|------|--------|
| Architecture Freeze Report (this) | `docs/implementation/WAVE_02_ARCHITECTURE_FREEZE_REPORT.md` | COMPLETE |
| Architecture Proposal (updated) | `docs/architecture/WAVE_02_REGISTRATION_ARCHITECTURE_PROPOSAL.md` | UPDATED |
| ADR | `docs/architecture/ADR_WAVE_02_IDENTITY_REGISTRATION.md` | PRODUCED |
| Threat Model | `docs/security/WAVE_02_REGISTRATION_THREAT_MODEL.md` | PRODUCED |
| Preflight Report (updated) | `docs/implementation/WAVE_02_PREFLIGHT_REPORT.md` | UPDATED |

---

## 35. GIT DIFF HYGIENE

```
git rev-parse HEAD: 075ed0a10999cbd8c9f506374069b807556b8673 — UNCHANGED
git status --short:
  ?? docs/architecture/WAVE_02_REGISTRATION_ARCHITECTURE_PROPOSAL.md
  ?? docs/implementation/WAVE_02_PREFLIGHT_REPORT.md
  (and new files below — all documentation only)
git diff --check: PASS (no whitespace errors)
```

**No application code modified.**
**No migrations added or executed.**
**No secrets.**
**No env files.**
**No SQL dumps.**
**No fabricated test results.**
**All Wave 1C/1B/1B.3/1A certified files: UNCHANGED (read-only forensic inspection).**

---

## 36. REMAINING RISKS

| Risk | Severity | Mitigation |
|------|----------|-----------|
| MIM SMS live credentials absent | MEDIUM | LogSmsProvider in dev/test; MIM pending configuration |
| Porichoy API contract absent | MEDIUM | NullIdentityVerificationProvider; interface deferred |
| OCR engine absent | MEDIUM | NullIdentityDocumentExtractor; interface deferred |
| `SENSITIVE_LOOKUP_SECRET` env management | HIGH | Must be provisioned securely before Wave 2A implementation; key rotation plan documented |
| `SESSION_HMAC_SECRET` env management | HIGH | Same as above |
| MariaDB `email` nullable unique behavior | LOW | Verified by design: NULL is NOT equal to NULL in SQL UNIQUE; safe |
| `mobile_canonical` uniqueness race | MEDIUM | DB constraint is final gate; application precheck insufficient alone |
| Cleanup job not yet designed | LOW | Deferred to Wave 2H; expired sessions do not expose data (token-validated) |
| PIN semantic undefined | LOW | Explicitly deferred; not implemented in Wave 2 |

---

## 37. DEFERRED ITEMS

| Item | Deferred To | Reason |
|------|------------|--------|
| Security PIN | Post-Wave 2 | Business semantics undefined — must PA define before implementation |
| MIM SMS live integration | Configuration task | API credentials and contract pending |
| Porichoy live integration | Configuration/contract task | Govt API contract pending |
| OCR auto-fill | Separate integration wave | No OCR engine present; interface boundary established |
| AWS KMS / HashiCorp Vault | Future infrastructure wave | Current APP_KEY-based encryption sufficient for Wave 2 |
| Redis session store | Production deployment | Array/file cache acceptable for dev/test; Redis required in production |
| WhatsApp/Push notifications | Future communication wave | Wave 1C extended in future |
| Commission/wallet/network | Future financial wave | |
| Full e-signature legal system | Future legal wave | |
| Subscription/billing | Future billing wave | |
| Full outbox for platform events | Future event wave | `outbox_events.tenant_id NOT NULL` blocks platform-level events |

---

## 38. IMPLEMENTATION AUTHORIZATION STATUS

**AUTHORIZED DOMAIN:** Planning and documentation only.
**NOT AUTHORIZED:** Application code, migrations, seeding, testing, git operations.
**Next step:** PA review of this freeze report → explicit authorization for Wave 2A implementation plan.

---

## 39. COMMIT STATUS

**COMMIT: NOT AUTHORIZED**
No commits have been made. HEAD: `075ed0a10999cbd8c9f506374069b807556b8673` — UNCHANGED.

---

## 40. PUSH STATUS

**PUSH: NOT AUTHORIZED**
No push has been made. Remote origin remains at Wave 1C certification commit.

---

## 41. FINAL VERDICT

```
═══════════════════════════════════════════════════════
RAISA ERP ENTERPRISE OS
WAVE 2 ARCHITECTURE FREEZE: PASS

All 7 PA decisions: RESOLVED / FROZEN
Repository forensic verification: COMPLETE
Existing auth flow conflicts: IDENTIFIED AND RESOLVED
Pre-user media boundary: OPTION A (STAGING ISOLATION) — SAFE
Certified foundations regression risk: NONE
Sensitive data architecture: DESIGNED (HMAC + application encryption)
OCR status: NOT PRESENT — null interface designed
Porichoy status: NOT PRESENT / CONFIG PENDING — null interface designed
Data model: 11 additive tables (earlier "13" was imprecise — corrected)
Enterprise User ID format: USR-YYYY-8CHAR — APPROVED
Account state machine: DEFINED (8 states, explicit transitions)
Authentication transition: SINGLE CREDENTIAL FIELD (mobile|email)
IAM boundary: VERIFIED — registration cannot inject roles/grants
Tenant provisioning: DEFERRED TO TenantProvisioningService (not Stage 1)
Implementation: NOT STARTED
Commit: NOT AUTHORIZED
Push: NOT AUTHORIZED

READY FOR PRINCIPAL ARCHITECT REVIEW OF WAVE 2A IMPLEMENTATION PLAN
═══════════════════════════════════════════════════════
```
