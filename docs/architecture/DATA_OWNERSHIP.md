# RAISA ERP — DATA OWNERSHIP: GLOBAL VS TENANT-MEMBERSHIP
**Version:** 1.0.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Principle (Invariant I30)

Data that describes a HUMAN BEING is globally owned by that user.
Data that describes a BUSINESS RELATIONSHIP with a tenant is tenant-membership owned.

These are NEVER mixed in the same table.

---

## 2. Global Personal Data (user-owned, platform-wide)

These records belong to the user globally. Access is RBAC-controlled,
not restricted to a specific tenant. The user carries these across all tenants.

```
users                    — Minimal account identity (mobile, email, password, MFA)
user_personal_profiles   — Personal attributes (DOB, gender, blood group, etc.)
user_personal_contacts   — Personal contact methods (secondary mobile, social links)
user_personal_addresses  — Personal addresses (home, permanent)
user_personal_kyc        — Government identity (NID, passport — RESTRICTED)
user_personal_documents  — Truly personal documents (birth cert, personal driving license)
```

### Schema

```sql
user_personal_profiles
  id, user_id UNIQUE FK, gender, religion, nationality, marital_status,
  dob DATE NULL, blood_group VARCHAR(5) NULL, place_of_birth VARCHAR(255) NULL,
  occupation VARCHAR(100) NULL, education VARCHAR(100) NULL,
  profession VARCHAR(100) NULL, bio TEXT NULL,
  emergency_contact_name VARCHAR(255) NULL,
  emergency_contact_mobile VARCHAR(20) NULL,
  photo_media_id CHAR(26) NULL,     -- via Media Engine
  signature_media_id CHAR(26) NULL,
  created_at, updated_at
  -- NO tenant_id — this is global personal data

user_personal_contacts
  id, user_id FK, contact_type VARCHAR(50), value VARCHAR(255),
  verified BOOLEAN DEFAULT FALSE, is_primary BOOLEAN DEFAULT FALSE,
  created_at, updated_at
  -- types: secondary_mobile, whatsapp, linkedin, facebook, telegram, website

user_personal_addresses
  id, user_id FK,
  label ENUM('home','permanent','other'),
  address_line1 VARCHAR(255), address_line2 VARCHAR(255) NULL,
  division VARCHAR(100) NULL, district VARCHAR(100) NULL,
  upazila VARCHAR(100) NULL, union_area VARCHAR(100) NULL,
  post_code VARCHAR(20) NULL, country CHAR(2) DEFAULT 'BD',
  lat DECIMAL(10,8) NULL, lng DECIMAL(11,8) NULL,  -- only with explicit consent
  is_primary BOOLEAN DEFAULT FALSE,
  created_at, updated_at

user_personal_kyc
  id, user_id UNIQUE FK,
  nid_number_encrypted TEXT NULL,    -- field-encrypted (AES-256-GCM)
  nid_number_hash VARCHAR(64) NULL,  -- SHA-256 of normalized NID for uniqueness check
  nid_number_masked VARCHAR(20) NULL, -- last 4 for display
  nid_status ENUM('unverified','pending','ocr_extracted','porichoy_verified','failed','manual_review'),
  passport_number_encrypted TEXT NULL,
  nid_front_media_id CHAR(26) NULL,
  nid_back_media_id  CHAR(26) NULL,
  ocr_data           JSON NULL,      -- SELF_DECLARED
  porichoy_response_encrypted TEXT NULL,
  porichoy_verified_at TIMESTAMP NULL,
  name_bn VARCHAR(255) NULL, name_en VARCHAR(255) NULL,
  father_name VARCHAR(255) NULL, mother_name VARCHAR(255) NULL,
  created_at, updated_at

user_personal_documents
  id, user_id FK,
  document_type VARCHAR(50),   -- 'birth_certificate', 'personal_driving_license', 'cv'
  document_number VARCHAR(255) NULL,
  issue_date DATE NULL, expiry_date DATE NULL,
  media_id CHAR(26) NULL,
  verified BOOLEAN DEFAULT FALSE,
  created_at, updated_at
```

---

## 3. Tenant-Membership Data (membership-owned)

These records describe the user's relationship with a specific tenant.
They belong to the membership, not the global user account.
A user leaving a tenant retains their global identity but loses membership data access.

```
tenant_memberships         — The relationship itself (user <-> tenant)
membership_profiles        — Business identity within the tenant (designation, employee code)
membership_addresses       — Work/office addresses for this membership
membership_bank_accounts   — Banking details as relevant for this membership (payroll, etc.)
membership_mfs_accounts    — MFS accounts used within this membership context
membership_documents       — Business documents for this membership role
membership_verifications   — Verification status within the tenant's context
membership_contracts       — Employment/dealer/franchise contracts (tenant-specific)
membership_employment      — Employment terms (salary, grade, joining date)
membership_business_profiles — Role-specific extended data (retailer shop, dealer territory, etc.)
```

### Schema

```sql
membership_profiles
  id, membership_id UNIQUE FK -> tenant_memberships.id,
  display_name VARCHAR(255) NULL,     -- how this person is known in this tenant
  internal_code VARCHAR(50) NULL,     -- employee code, customer code, dealer code
  designation_title VARCHAR(100) NULL,
  department_id CHAR(26) NULL,
  branch_id CHAR(26) NULL,
  manager_id CHAR(26) NULL FK -> users.id,
  created_at, updated_at
  INDEX idx_mp_membership (membership_id)

membership_addresses
  id, membership_id FK, label ENUM('office','delivery','billing','other'),
  -- address fields same as user_personal_addresses
  created_at, updated_at

membership_bank_accounts
  id, membership_id FK,
  purpose ENUM('salary','refund','settlement','other'),
  bank_name VARCHAR(100), branch_name VARCHAR(100),
  account_name VARCHAR(200), account_number_encrypted TEXT,  -- field-encrypted
  routing_number VARCHAR(20) NULL, iban VARCHAR(50) NULL, swift VARCHAR(20) NULL,
  is_primary BOOLEAN DEFAULT FALSE, verified BOOLEAN DEFAULT FALSE,
  created_at, updated_at

membership_mfs_accounts
  id, membership_id FK,
  provider ENUM('bkash','nagad','rocket','upay','other'),
  mobile VARCHAR(20), purpose VARCHAR(50) NULL,
  is_primary BOOLEAN DEFAULT FALSE, verified BOOLEAN DEFAULT FALSE,
  created_at, updated_at

membership_documents
  id, membership_id FK,
  document_type VARCHAR(50),   -- 'employment_contract', 'dealer_agreement', 'trade_license_copy'
  document_number VARCHAR(255) NULL,
  issue_date DATE NULL, expiry_date DATE NULL,
  media_id CHAR(26) NULL,
  verified BOOLEAN DEFAULT FALSE,
  created_at, updated_at

membership_contracts
  id, membership_id FK,
  contract_type VARCHAR(50),          -- 'employment', 'dealer', 'franchise', 'service'
  contract_version VARCHAR(20),
  accepted_at TIMESTAMP,
  ip_address VARCHAR(45),
  user_agent TEXT,
  digital_signature TEXT NULL,
  media_id CHAR(26) NULL,             -- signed contract file
  created_at
  -- IMMUTABLE: no updated_at, no deleted_at

membership_employment
  id, membership_id UNIQUE FK,
  joining_date DATE NOT NULL,
  employment_status ENUM('probation','permanent','contract','resigned','terminated'),
  contract_end_date DATE NULL,
  salary_grade_id CHAR(26) NULL,
  cost_center_id CHAR(26) NULL,
  created_at, updated_at

membership_business_profiles
  id, membership_id FK,
  profile_type VARCHAR(50),           -- 'retailer', 'dealer', 'entrepreneur', 'supplier', etc.
  data JSON NOT NULL,                 -- flexible schema per profile_type
  created_at, updated_at
```

---

## 4. Company / Legal Entity Data

Trade Licenses, TIN, BIN, VAT certificates belong to COMPANIES, not to users.

```
companies
  id, tenant_id FK, name_en, name_bn, legal_type,
  registration_number VARCHAR(100) NULL,  -- RJSC / MRA / other
  tin_number_encrypted TEXT NULL,         -- field-encrypted
  tin_number_masked VARCHAR(20) NULL,
  bin_number VARCHAR(50) NULL,
  vat_registered BOOLEAN DEFAULT FALSE,
  business_type_key VARCHAR(100),
  address_line1, division, district, upazila, post_code,
  created_at, updated_at

company_licenses
  id, company_id FK,
  license_type VARCHAR(50),    -- 'trade_license', 'drug_license', 'fire_license', etc.
  license_number VARCHAR(100),
  issuing_authority VARCHAR(200),
  issue_date DATE, expiry_date DATE NULL,
  media_id CHAR(26) NULL,
  created_at, updated_at
```

TIN/BIN/Trade License → `companies` (not `users`, not `membership_documents`)

---

## 5. Data Ownership Matrix

| Data | Owner | Table | Access |
|------|-------|-------|--------|
| Mobile number | Global User | users | Via RBAC policy |
| NID number | Global User | user_personal_kyc | RESTRICTED |
| Home address | Global User | user_personal_addresses | Via RBAC policy |
| Employee salary | Tenant Membership | membership_employment | Tenant RBAC |
| Work address | Tenant Membership | membership_addresses | Tenant RBAC |
| Dealer territory | Tenant Membership | membership_business_profiles | Tenant RBAC |
| Employment contract | Tenant Membership | membership_contracts | Tenant RBAC |
| Salary bank account | Tenant Membership | membership_bank_accounts | Tenant RBAC |
| TIN certificate | Company | company_licenses | Tenant Admin |
| Trade license | Company | company_licenses | Tenant Admin |
| BIN number | Company | companies.bin_number | Tenant Admin |
| Franchise agreement | Tenant Membership | membership_contracts | Tenant RBAC |

---

## 6. Data Access Rules

- A **user** can view their own global personal data from any tenant context.
- A **tenant admin** cannot see a user's global personal data without authorization.
- **NID data** (RESTRICTED) requires explicit permission even for tenant admins.
- **Membership data** is visible within the tenant's RBAC context.
- On membership revocation: membership data is preserved but access removed.
- On global account deletion: global personal data anonymized; membership data retained for audit.

---

*Document Owner: Principal Architect + Privacy Architect | v1.0.0 | Invariant: I30*
