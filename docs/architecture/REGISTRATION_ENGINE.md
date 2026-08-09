# RAISA ERP — REGISTRATION ENGINE
**Version:** 1.2.0 | **Date:** 2026-08-09 | **Phase:** 00B

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | Global user identity, immutable USR-ID separate from role codes, media/OTP prerequisites documented, membership model |

---

## 1. Canonical Registration Engine (I15)

ONE registration engine. ALL registration sources use it. No duplicates.

### Registration Sources
Public | Tenant Invitation | HR | Employee | Dealer | Entrepreneur |
District Depo | Thana Dealer | Union Dealer | Agent | Retailer | Customer |
Supplier/Vendor | Director | Shareholder | Warehouse | Delivery | Partner |
Franchise | Referral | API | Mobile App | QR Code | CRM Lead Conversion

---

## 2. Prerequisites (CRITICAL — DO NOT START REGISTRATION BEFORE THESE EXIST)

Registration Engine DEPENDS on:
- **Media Engine** (Wave 1B) — for photo/NID uploads via canonical secure path
- **OTP/SMS Service** (Wave 1C) — for mobile OTP verification
- **RBAC foundation** (Wave 1A) — for role assignment

These are NOT workarounds. No temporary uploader. No temporary OTP service.
Registration Wave (W2) DOES NOT BEGIN until W1A, W1B, W1C are certified. (I16)

---

## 3. Global User ID (REVISED v1.1.0)

### USR-ID: Immutable Global Identity

```
Format: USR-{YEAR}-{8_CHAR_RANDOM_ALPHANUMERIC}
Example: USR-2026-Q8M7R2P4

Generated: ONCE at account creation. NEVER changes.
Purpose:   Globally unique, immutable human identity reference.
Scope:     Platform-wide. Not tenant-specific.
```

```php
class GlobalUserIdGenerator
{
    // Format: USR-YYYY-XXXXXXXX  (one generator, used everywhere — I15)
    public function generate(): string
    {
        do {
            $candidate = 'USR-' . now()->format('Y') . '-' . strtoupper(Str::random(8));
        } while (User::where('global_user_id', $candidate)->exists());
        return $candidate;
    }
}
```

### Role/Position Reference Numbers (Separate from Identity)

Role-based reference numbers are TENANT-SCOPED display labels, NOT global identity.

```
Format: {POSITION_CODE}-{YEAR}-{8_CHAR_RANDOM}
Example: TA-2026-K3P9X1M5      (Tenant Admin position within a tenant)
         DIR-FIN-2026-B7N2W4F1  (Finance Director position)
         DD-2026-R5Q8T2Y6       (District Dealer position)
         CUST-2026-L4M7P1N3     (Customer position)
```

These are generated per-position-assignment, stored in position_assignments.reference_number.
A user may have multiple such codes (multiple tenants, multiple positions).
Changing role does NOT change global_user_id.

---

## 4. Step 1: Account Identity (Mobile-First)

### Prerequisites: OTP Service (W1C) must be certified first.

```
1. Country selector (default: BD +880)
2. Mobile number input (validated format: 01XXXXXXXXX for BD)
3. [Send OTP] button
   -> POST /api/v1/auth/otp/send { country: "+880", mobile: "01712345678" }
   -> Server: validate format
   -> Server: check uniqueness (mobile not already registered, or return "account exists")
   -> Rate limit: max 3 OTP requests per 10 minutes per mobile
   -> OTP Service: generate 6-digit code (server-side, CSPRNG)
   -> Store: otp_records { mobile, otp_hash (bcrypt), expires_at (+5min), attempts: 0 }
   -> SMS Provider Adapter: send OTP (fire and forget to queue)
   -> Return: { sent: true, expires_in: 300, resend_cooldown: 60 }

4. OTP entry (6 digits, numeric)
   -> POST /api/v1/auth/otp/verify { mobile, otp }
   -> Server: find otp_records by mobile
   -> Check: not expired, attempts < 5
   -> Verify: bcrypt_check(otp, otp_hash)
   -> On failure: increment attempts; if >= 5 lock for 15 minutes
   -> On success: mark otp_record as consumed
   -> DO NOT LOG OTP VALUE (I10)

5. On OTP success:
   -> Generate global_user_id: USR-2026-XXXXXXXX
   -> Create users record (status=pending)
   -> Create authenticated session
   -> Bind to session

6. Photo upload (via Media Engine — W1B must exist)
   -> UI: photo picker + camera capture option
   -> Media Engine preflight -> signed upload -> quarantine -> async validation

7. NID Front photo upload (via Media Engine — RESTRICTED classification)
8. NID Back photo upload (via Media Engine — RESTRICTED classification)

9. [Auto-fill from NID] button (if OCR adapter configured)
   -> POST /api/v1/media/{intent_id}/ocr-extract
   -> Returns extracted fields (SELF_DECLARED, not verified)
   -> Requires Media Engine + AI OCR adapter (W1B + W17 or stub)
   -> If OCR unavailable: button disabled with tooltip "OCR not configured"

10. [Verify with Govt. Porichoy] button (if authorized credentials present)
    -> POST /api/v1/kyc/porichoy-verify
    -> IF credentials absent: button disabled, status = OCR_EXTRACTED only
    -> NEVER fake success (I11, I12)

11. NID-derived fields (displayed, editable, flagged as SELF_DECLARED or GOVT_VERIFIED):
    - Bangla Name, English Name, Father, Mother, DOB, NID No,
    - Blood Group, Place of Birth, Present Address, Permanent Address

12. Email (optional but recommended)
    -> Unique platform-wide check
    -> Verification email sent via Email Service (W1C must support email queuing)

13. Password (optional if OTP-only login preferred by platform policy)
    -> Confirm password
    -> Policy: min 8 chars, mixed case + digit

14. Optional: Security PIN (6 digits, hashed separately)
15. Optional: MFA enrollment (TOTP — show QR, verify code, save recovery codes)

After Step 1 completion:
  users.status = 'active'
  Audit log: USER_REGISTERED
  Welcome notification (if SMS/email configured)
```

---

## 5. Step 2: Tenant Membership

After global account creation, user may:
a. Accept a tenant invitation (invited_by existing member)
b. Create a new tenant (becomes Tenant Admin, creates tenant_memberships record)
c. Join via QR/link (pre-authorized tenant invitation)

```
On tenant invitation acceptance:
  -> Verify invitation token (server-side, time-limited)
  -> Create tenant_memberships { user_id, tenant_id, status=active }
  -> Assign initial roles via tenant_membership_roles
  -> Generate position_assignment reference number (e.g., CUST-2026-XXXXXXXX)
  -> Set active_tenant_sessions for current session
  -> Redirect to profile completion wizard (Step 3)
```

---

## 6. Step 3: Universal Enterprise Profile Wizard

Shown after first tenant access. Sections displayed per role/capability.

```
PERSONAL
  Gender, Religion, Nationality, Marital Status, Occupation,
  Education, Profession, Emergency Contact (name, mobile)

CONTACT
  Primary Mobile (from Step 1, read-only), Secondary Mobile, WhatsApp,
  Email, Alternative Email, Website, Social links

ADDRESS (via user_addresses — multiple, type-tagged)
  Type: present | permanent | office | shipping | billing
  Fields: Division, District, Upazila, Union, Village/Area,
          Street/Road, Holding/Flat, Post Code
  Optional: map pin (with explicit user consent + business purpose disclosure)

BANKING (via membership_bank_accounts — RESTRICTED)
  Bank, Branch, Account Name, Account Number (field-encrypted), Routing, IBAN, SWIFT

MFS (via membership_mfs_accounts)
  bKash, Nagad, Rocket, Upay, other

LEGAL / KYC DOCUMENTS (via user_documents + Media Engine)
  TIN Certificate, BIN Certificate, Trade License,
  Driving License, Birth Certificate, Company Registration,
  VAT Certificate, Import/Export License, Passport

EMPLOYMENT (via employment_details — tenant-scoped)
  Department, Designation, Joining Date, Employee Code,
  Manager / Reporting To, Salary Grade, Employment Status

ROLE-SPECIFIC DATA (via position-type-specific extension tables)
  Retailer:       Shop Name, Shop Address, Shop Type, Area
  Dealer:         Territory, License No, Commission Group, Sponsor Code
  Entrepreneur:   Sponsor Code, Investment Amount, Referral Code, Rank
  Supplier:       Supply Categories, Lead Time, Payment Terms, Credit Limit
  Director:       Board Position, Share Count, Voting Rights
  Shareholder:    Share Class, Investment, Dividend Preference
  Delivery:       Vehicle Type, License Plate, License No, Zone, COD Limit
  Doctor:         BMDC Registration, Specialty, Chamber, Schedule
  Teacher:        Institute, Department, Subjects Taught
  Hotel Manager:  Property Assignment, Floor, Room Count
  Property Mgr:   Properties, Units, Zone

DOCUMENTS (via Media Engine — type-tagged uploads)
  Profile Photo (update), Signature, CV/Resume, Certificates, Agreements

DIGITAL CONTRACT (via user_contract_acceptances — IMMUTABLE)
  Privacy Policy (version, timestamp, IP, device, digital signature)
  Terms of Service (version, timestamp, IP, device, digital signature)
  Role-specific agreement (Employment Contract, Dealer Agreement, etc.)
```

---

## 7. Profile Completion Engine

Completion is calculated from requirement matrices per role — NOT hardcoded percentages.

```php
class ProfileCompletionService
{
    public function calculate(User $user, string $tenantId): ProfileCompletion
    {
        $roleKeys = $this->getUserRoleKeys($user->id, $tenantId);
        $requirements = $this->getRequirements($roleKeys);
        $satisfied = $requirements->filter(fn($req) => $this->isSatisfied($req, $user, $tenantId));

        $percentage = $requirements->isEmpty()
            ? 100
            : (int) round(($satisfied->count() / $requirements->count()) * 100);

        return new ProfileCompletion(
            percentage: $percentage,
            unsatisfied: $requirements->diff($satisfied),
            gatingLevel: $this->resolveGatingLevel($percentage, $roleKeys)
        );
    }
}
```

### Access Gating (policy-driven, configurable per role)
```
Step 1 complete (mobile verified):  login allowed
0-29%:    limited/read-only features
30-59%:   basic operational features
60-79%:   department/business features
80-99%:   financial features
100%:     full role capabilities
```

Dashboard warning: "Complete your profile to unlock all features." with specific missing items.

---

## 8. KYC Status States

```
NID_UNVERIFIED        User has not submitted NID
NID_PENDING           NID photos submitted, awaiting processing
NID_OCR_EXTRACTED     Auto-filled from OCR (SELF_DECLARED — not government verified)
NID_PORICHOY_VERIFIED Verified with Govt. Porichoy API (GOVT_VERIFIED)
NID_FAILED            Verification failed — reason documented
NID_MANUAL_REVIEW     Flagged for manual admin review
```

Display clearly in UI. Never show PORICHOY_VERIFIED if not actually verified. (I12)

---

## 9. Uniqueness Constraints

```
users.mobile:        UNIQUE platform-wide (DB constraint)
users.email:         UNIQUE platform-wide (DB constraint)
users.global_user_id: UNIQUE platform-wide (DB constraint)
user_kyc_records.nid_number: Application-enforced uniqueness (field-encrypted, cannot use DB UNIQUE)
user_documents.document_number: Application-enforced uniqueness per document_type
position_assignments.reference_number: UNIQUE platform-wide (DB constraint)
```

NID uniqueness for encrypted fields: application-level check using encrypted comparison or hash-of-normalized-value.

---

---

## 10. Table Name Alignment (NEW v1.2.0)

Global personal tables (user-owned):
  user_personal_profiles, user_personal_contacts, user_personal_addresses,
  user_personal_kyc, user_personal_documents

Tenant-membership tables (step 2/3 of registration):
  tenant_memberships, membership_profiles, membership_addresses,
  membership_bank_accounts, membership_mfs_accounts, membership_documents,
  membership_contracts, membership_employment, membership_business_profiles

Company-owned (NOT on user):
  company_licenses (TIN, Trade License, VAT) — on companies table

(I30) See DATA_OWNERSHIP.md.

Position assignments:
  position_assignments — effective-dated, new record per promotion
  Position reference number is NOT identity (I24, I35)

*Document Owner: Principal Architect | v1.2.0 | Invariants: I10, I11, I12, I15, I16, I23, I24, I30, I35*


