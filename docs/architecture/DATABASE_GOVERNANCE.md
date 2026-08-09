# RAISA ERP — DATABASE GOVERNANCE
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00A

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | Normalized user/identity model, money model resolution, MySQL isolation rules |

---

## 1. Engine & Version

- **RDBMS:** MySQL 8.x (8.0.28+ minimum, 8.4 LTS preferred)
- **Character Set:** utf8mb4
- **Collation:** utf8mb4_unicode_ci
- **Storage Engine:** InnoDB (mandatory — FK support, ACID transactions)

---

## 2. Primary Key Strategy

**Decision: ULIDs stored as CHAR(26)**

- Sortable, unique across distributed contexts, no sequential ID enumeration
- Laravel: `use Illuminate\Database\Eloquent\Concerns\HasUlids;`
- All main entity tables use ULID PKs

Exception: junction/pivot tables may use composite PK.

---

## 3. Normalized User / Identity Schema (REVISED v1.1.0)

The users table is GLOBAL (no tenant_id). It is the minimal account identity only.
Extended data lives in normalized tables.

```sql
-- GLOBAL identity (no tenant_id)
users
  id                  CHAR(26) PK
  global_user_id      VARCHAR(20) UNIQUE NOT NULL  -- USR-2026-XXXXXXXX, immutable
  mobile              VARCHAR(20) UNIQUE NOT NULL
  mobile_verified     BOOLEAN DEFAULT FALSE
  mobile_verified_at  TIMESTAMP NULL
  email               VARCHAR(255) UNIQUE NULL
  email_verified      BOOLEAN DEFAULT FALSE
  email_verified_at   TIMESTAMP NULL
  password_hash       VARCHAR(255) NULL  -- bcrypt(12)
  status              ENUM('pending','active','suspended','banned') DEFAULT 'pending'
  mfa_enabled         BOOLEAN DEFAULT FALSE
  mfa_secret          VARCHAR(255) NULL  -- encrypted
  created_at, updated_at, deleted_at

-- Extended profile (one-to-one per user, NOT tenant-scoped)
user_profiles
  id, user_id UNIQUE FK, gender, religion, nationality, marital_status,
  occupation, education, profession, bio, emergency_contact_name,
  emergency_contact_mobile, created_at, updated_at

-- Addresses (one-to-many per user, type-tagged)
user_addresses
  id, user_id FK, type ENUM('present','permanent','office','shipping','billing'),
  division, district, upazila, union_area, village_area, street, holding_flat,
  post_code, country CHAR(2) DEFAULT 'BD',
  lat DECIMAL(10,8) NULL, lng DECIMAL(11,8) NULL,  -- only with explicit consent
  is_primary BOOLEAN DEFAULT FALSE,
  created_at, updated_at

-- Contact methods
user_contacts
  id, user_id FK, contact_type VARCHAR(50), value VARCHAR(255),
  verified BOOLEAN DEFAULT FALSE, is_primary BOOLEAN DEFAULT FALSE,
  created_at, updated_at
  -- types: secondary_mobile, whatsapp, linkedin, facebook, website, etc.

-- Banking details (RESTRICTED — field-encrypted)
user_banking_details
  id, user_id FK, bank_name, branch_name,
  account_name,
  account_number VARCHAR(255) -- field-encrypted (CipherSweet or Laravel encryption)
  routing_number VARCHAR(100), iban VARCHAR(100), swift VARCHAR(20),
  is_primary BOOLEAN, verified BOOLEAN, created_at, updated_at

-- MFS accounts (CONFIDENTIAL)
user_mfs_accounts
  id, user_id FK,
  provider ENUM('bkash','nagad','rocket','upay','other'),
  mobile VARCHAR(20), is_primary BOOLEAN, verified BOOLEAN,
  created_at, updated_at

-- KYC verification state
user_kyc_records
  id, user_id UNIQUE FK,
  nid_number VARCHAR(255) NULL,   -- field-encrypted
  nid_number_masked VARCHAR(20) NULL,  -- last 4 for display
  nid_status ENUM('unverified','pending','ocr_extracted','porichoy_verified','failed','manual_review'),
  passport_number VARCHAR(255) NULL,  -- field-encrypted
  nid_front_media_id CHAR(26) NULL FK -> media_uploads.id,
  nid_back_media_id  CHAR(26) NULL FK -> media_uploads.id,
  photo_media_id     CHAR(26) NULL FK -> media_uploads.id,
  ocr_data           JSON NULL,       -- extracted fields (not authoritative)
  porichoy_response  JSON NULL,       -- encrypted, from Govt. API
  porichoy_verified_at TIMESTAMP NULL,
  name_bn            VARCHAR(255) NULL,
  name_en            VARCHAR(255) NULL,
  father_name        VARCHAR(255) NULL,
  mother_name        VARCHAR(255) NULL,
  dob                DATE NULL,
  blood_group        VARCHAR(5) NULL,
  place_of_birth     VARCHAR(255) NULL,
  created_at, updated_at

-- Legal / professional documents
user_documents
  id, user_id FK, document_type VARCHAR(50), document_number VARCHAR(255),
  issue_date DATE NULL, expiry_date DATE NULL,
  media_id CHAR(26) NULL FK -> media_uploads.id,
  verified BOOLEAN DEFAULT FALSE, created_at, updated_at
  -- types: tin, bin, trade_license, driving_license, birth_cert, etc.

-- Digital contract acceptance audit
user_contract_acceptances
  id, user_id FK, contract_type VARCHAR(50), contract_version VARCHAR(20),
  accepted_at TIMESTAMP, ip_address VARCHAR(45), user_agent TEXT,
  device_fingerprint VARCHAR(255), digital_signature TEXT NULL,
  created_at
  -- IMMUTABLE: no updated_at, no deleted_at

-- Tenant membership (many-to-many users<->tenants)
tenant_memberships (see MULTI_TENANCY.md)

-- Active tenant context per session
active_tenant_sessions (see MULTI_TENANCY.md)

-- Position / role codes within a tenant (display, NOT identity)
position_assignments (see MULTI_TENANCY.md)

-- Role grants within a membership (scoped)
tenant_membership_roles (see MULTI_TENANCY.md)

-- Employment details (tenant-scoped — belongs to tenant, not global)
employment_details
  id, tenant_id FK, user_id FK, company_id FK, branch_id FK,
  department_id FK, designation_id FK, manager_id NULL FK -> users.id,
  employee_code VARCHAR(50),   -- unique per tenant
  joining_date DATE, employment_status ENUM('probation','permanent','contract','resigned','terminated'),
  salary_grade_id FK, created_at, updated_at
  UNIQUE KEY uq_emp_code_tenant (tenant_id, employee_code)
```

### Key Principles

- `users` has NO tenant_id. It is global.
- All tenant-specific user data lives in tenant-scoped tables (employment_details, etc.)
- All global user data (profile, kyc, addresses) is NOT tenant-scoped but access is RBAC-controlled.
- NID numbers, passport numbers, bank account numbers are field-encrypted.

---

## 4. Canonical Money Model (REVISED v1.1.0)

ONE canonical money model. No mixing or improvisation.

### 4.1 Rules

| Rule | Decision |
|------|----------|
| Computation | Integer minor units (paisa for BDT). NEVER floating point arithmetic. |
| Storage | DECIMAL(20,6) in MySQL. Never FLOAT. Never DOUBLE. |
| High-precision | DECIMAL(20,10) value objects for FX rates, tax rates, interest rates. |
| Rounding | HALF_UP (0.5 rounds away from zero). Applied ONCE at final output. |
| Currency scale | From ISO 4217: BDT=2 (paisa), USD=2, EUR=2, JPY=0. |
| Tax rounding | Round HALF_UP per line item, then sum. Never sum-then-round. |
| Invoice total | Round to currency scale HALF_UP at invoice finalization. |
| JSON API | Amounts serialized as STRING ("1234.50"). Never numeric float. |
| Overflow | DECIMAL(20,6) supports 14 integer digits — sufficient for all realistic amounts. |

### 4.2 Money Value Object (PHP)

```php
// Canonical Money value object — computation in integer minor units
final class Money
{
    private function __construct(
        private readonly int $minorUnits,   // e.g., 123450 paisa = 1234.50 BDT
        private readonly string $currency,  // ISO 4217
    ) {}

    public static function ofMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, $currency);
    }

    public static function fromDecimalString(string $decimal, string $currency): self
    {
        $scale = CurrencyScale::of($currency); // e.g., 2 for BDT
        // Convert string to integer minor units using bcmath — no float
        $minorUnits = (int) bcmul($decimal, (string) (10 ** $scale), 0);
        return new self($minorUnits, $currency);
    }

    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function multiply(string $multiplier): self
    {
        // bcmath for exact arithmetic
        $result = bcmul((string) $this->minorUnits, $multiplier, 0);
        return new self((int) $result, $this->currency);
    }

    public function toDecimalString(): string
    {
        $scale = CurrencyScale::of($this->currency);
        return bcdiv((string) $this->minorUnits, (string) (10 ** $scale), $scale);
    }

    public function toMinorUnits(): int { return $this->minorUnits; }
    public function currency(): string { return $this->currency; }
}
```

### 4.3 Database Storage

```sql
-- Standard financial amount column
amount      DECIMAL(20, 6) NOT NULL
currency    CHAR(3) NOT NULL DEFAULT 'BDT'

-- High-precision rate column (FX rate, tax rate, interest rate)
rate        DECIMAL(20, 10) NOT NULL

-- FORBIDDEN:
amount FLOAT          -- precision loss
amount DOUBLE         -- precision loss
amount DECIMAL(10,2)  -- insufficient precision
```

---

## 5. Mandatory Column Standards

Every tenant-scoped table MUST have:
```sql
tenant_id   CHAR(26) NOT NULL  INDEX   -- FK -> tenants.id
created_at  TIMESTAMP NOT NULL
updated_at  TIMESTAMP NOT NULL
```

Immutable tables (ledger_entries, audit_logs, user_contract_acceptances):
```sql
-- NO updated_at. NO deleted_at. NO soft delete.
created_at  TIMESTAMP NOT NULL  -- only timestamp allowed
```

---

## 6. Indexing Standards

```sql
-- Every tenant-scoped table
INDEX idx_{table}_tenant (tenant_id)

-- Common composite patterns
INDEX idx_{table}_tenant_status (tenant_id, status)
INDEX idx_{table}_tenant_created (tenant_id, created_at DESC)

-- All FK columns must be indexed
-- Unique constraints include tenant_id where uniqueness is per-tenant
UNIQUE KEY uq_invoice_tenant_number (tenant_id, invoice_number)
UNIQUE KEY uq_sku_tenant (tenant_id, sku)
```

---

## 7. Schema Patterns

### 7.1 Universal Commercial Item Engine

```sql
-- Core record (all item types share this)
commercial_items
  id, tenant_id, item_type, sku, name_en, name_bn,
  category_id, brand_id, unit_id, tax_class_id,
  status ENUM('active','inactive','archived'),
  stock_behavior ENUM('tracked','untracked','service'),
  created_at, updated_at, deleted_at
  INDEX idx_ci_tenant_type (tenant_id, item_type)
  UNIQUE KEY uq_sku_tenant (tenant_id, sku)

-- Type-specific extension tables (one-to-one with commercial_items)
commercial_item_physical_attrs    -- weight, dimensions, barcode
commercial_item_medicine_attrs    -- generic_name, dosage, rx_required
commercial_item_food_attrs        -- allergens, nutrition, prep_time
commercial_item_service_attrs     -- duration, delivery_method
commercial_item_room_attrs        -- floor, max_occupancy, bed_type
commercial_item_property_attrs    -- area_sqft, floors, land_area
commercial_item_vehicle_attrs     -- make, model, year, plate
commercial_item_subscription_attrs -- billing_period, trial_days
commercial_item_batch_attrs       -- batch_tracking, expiry_tracking
commercial_item_serial_attrs      -- serial_tracking, imei_tracking
```

### 7.2 Ledger (Immutable)

```sql
ledger_entries
  id              CHAR(26) PK ULID
  tenant_id       CHAR(26) NOT NULL INDEX
  ledger_account_id CHAR(26) NOT NULL
  entity_type     VARCHAR(50) NOT NULL
  entity_id       CHAR(26) NOT NULL
  direction       ENUM('DEBIT','CREDIT') NOT NULL
  amount          DECIMAL(20,6) NOT NULL          -- NEVER FLOAT
  currency        CHAR(3) NOT NULL
  idempotency_key VARCHAR(128) UNIQUE NOT NULL
  note            TEXT NULL
  metadata        JSON NULL
  posted_by       CHAR(26) NULL
  created_at      TIMESTAMP NOT NULL
-- NO updated_at. NO deleted_at. Corrections via reversal entries.
```

### 7.3 Audit Log (Immutable)

```sql
audit_logs
  id              BIGINT UNSIGNED AUTO_INCREMENT PK
  tenant_id       CHAR(26) NULL
  user_id         CHAR(26) NULL
  event           VARCHAR(100) NOT NULL
  auditable_type  VARCHAR(50) NULL
  auditable_id    CHAR(26) NULL
  old_values      JSON NULL
  new_values      JSON NULL
  ip_address      VARCHAR(45) NULL
  user_agent      TEXT NULL
  extra           JSON NULL
  created_at      TIMESTAMP NOT NULL
-- NO updated_at. NO deleted_at.
```

---

## 8. Migration Governance

1. Migrations are one-way in production. Down() for dev rollback only.
2. Never drop columns without a deprecation cycle.
3. Large table changes (>1M rows) use zero-downtime strategies.
4. No migration modifies ledger_entries or audit_logs structure.
5. All migrations reviewed by DB Architect before wave certification.

---

## 9. Soft Delete Policy

| Table | Soft Delete | Rationale |
|-------|-------------|-----------|
| users | YES | Legal recovery, GDPR |
| tenants | YES | Recovery |
| companies/branches | YES | Recovery |
| commercial_items | YES | History |
| invoices/orders | NO | Financial records — use status |
| ledger_entries | NEVER | Immutable |
| audit_logs | NEVER | Immutable |
| user_contract_acceptances | NEVER | Legal evidence |
| stock_movements | NEVER | Correction via reversal |

---

*Document Owner: Database Architect | v1.1.0 | Review: Each wave*
