# RAISA ERP — IMPLEMENTATION WAVE PLAN
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00A

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial wave plan |
| 1.1.0 | 2026-08-09 | CRITICAL: Split W1 into W1, W1A, W1B, W1C. Fixed W2 prerequisites. Global identity model. |

---

## Dependency Invariant

Registration (W2) REQUIRES:
- W1A: Tenant Context + RBAC (for role assignment during registration)
- W1B: Certified Media Gateway Core (for photo/NID upload during Step 1)
- W1C: OTP/Communication Core (for mobile OTP — the very first step)

No temporary uploaders. No temporary OTP systems. (I15, I16)

---

## WAVE 0 — Architecture Constitution (COMPLETE)
**Status:** COMPLETE (Phase 00 + Phase 00A)
**Deliverable:** Architecture documents, Constitution v1.1.0, Wave plan

---

## WAVE 1 — Core Platform Foundation
**Dependencies:** Wave 0
**Critical Path:** YES — ALL subsequent waves depend on this

### Scope: Backend Foundation
- Scaffold `app/Domain/` directory structure
- Configure MySQL (migrate from SQLite default for prod)
- Install Redis + configure as cache + queue driver
- Install: `spatie/laravel-permission`, `laravel/sanctum`
- Install: `myclabs/php-enum` or PHP 8.1+ native enums
- Install: ULID support (`symfony/uid` or `laravel/framework` built-in)
- Create `routes/api.php` with versioned prefix `/api/v1/`
- Global exception handler → standardized API error responses
- Structured logging configuration (JSON for production)
- Money value object (integer minor units + bcmath, DECIMAL(20,6) storage)
- Currency scale registry (BDT=2, USD=2, JPY=0, etc.)
- Shared Kernel value objects: TenantId, UserId, Money

### Scope: Frontend Foundation
- BEFDS CSS token system (variables: colors, spacing, typography, shadows, radii)
- Self-hosted fonts: Inter + Hind Siliguri (WOFF2, subsetted)
- Font loading: `@font-face` in app.css, no Google Fonts runtime fetch
- Tailwind config aligned with BEFDS tokens
- Dark/light mode refinement for BEFDS palette
- App shell structure (sidebar container, main content area, header)
- BEFDS sidebar (dark blue #122240, collapsible, icon+label, mobile drawer)
- i18n infrastructure: `react-i18next` + `en.json` + `bn.json` base files
- Language toggle component (persisted per user)
- Refactor `welcome.tsx` (76KB) into proper BEFDS components
- Rename `ssr.jsx` → `ssr.tsx` with proper TypeScript
- TypeScript path aliases review (@/* -> resources/js/*)

### Scope: Testing / CI Foundation
- Vitest configuration for frontend unit tests
- Playwright setup + basic smoke test (app loads)
- GitHub Actions CI: PHP tests + TypeScript type-check + ESLint + Prettier + security audit
- Verify test suite runs on MySQL (not just SQLite in-memory)

### NOT in Wave 1: Any business domain, auth, users, OTP, media, tenancy

---

## WAVE 1A — Tenant Context + RBAC Primitives
**Dependencies:** Wave 1
**Critical Path:** YES — W1B, W1C, W2 depend on this

### Scope
- `tenants` table migration (id, name, slug, status, plan_id, settings, etc.)
- `tenant_memberships` migration (user_id, tenant_id, status, invited_by, joined_at)
- `tenant_membership_roles` migration (membership_id, role_key, company_id, branch_id, etc.)
- `active_tenant_sessions` migration (user_id, session_id, tenant_id)
- `position_assignments` migration (user_id, tenant_id, position_code, reference_number, effective_from, effective_to, status — effective-dated)
- Spatie permission tables (roles, permissions, model_has_roles, etc.)
- Role definitions seeder: SA, TA, initial roles
- Permission seeder: initial platform permissions
- `ResolveTenantContext` middleware
- `TenantScope` global query scope trait
- `CapabilityResolutionService` (basic — full capability engine in W3)
- Platform-level tenant provisioning service (creates tenant + initial TA membership)
- Tenant context tests: isolation tests, membership tests, active-tenant-session tests

---

## WAVE 1B — Certified Media Gateway Core
**Dependencies:** Wave 1A
**Critical Path:** YES — W2 (Registration) requires this for photo/NID upload

### Scope (Media Engine — the FULL canonical implementation)
- `media_uploads` table migration
- Upload Preflight API: `POST /api/v1/media/preflight`
  - Validates: entity_type, content_type whitelist, max size by type
  - Generates: signed upload URL (S3 or local signed token for dev)
  - Creates: media_upload_intent record (status=PENDING)
- Dev storage adapter: local disk with signed token (no S3 required for dev)
- Production storage adapter: S3-compatible (configurable in .env)
- `ProcessMediaUploadJob` (async worker):
  - Extension whitelist check
  - MIME detection (PHP finfo — NOT browser-supplied)
  - Magic byte signature verification
  - Image: decode, dimension check, EXIF strip, re-encode, WebP variants
  - Document: basic validation
  - Status: PENDING -> PROCESSING -> APPROVED or REJECTED
- Signed Delivery URL API: `GET /api/v1/media/{id}/url`
  - Requires authentication + authorization
  - Returns short-lived signed URL (5-15 min)
  - NEVER permanent public URL for restricted files (I08)
- `FileUpload` BEFDS component (preflight -> signed upload -> completion notify)
- Media security adversarial test suite (PHP file rejection, path traversal, etc.)
- Cross-tenant media access negative tests

---

## WAVE 1C — OTP / Communication Provider Core
**Dependencies:** Wave 1A
**Critical Path:** YES — W2 (Registration) requires OTP on first step

### Scope
- `otp_records` table migration
  - (id, mobile, otp_hash, purpose, expires_at, attempts, consumed, consumed_at, created_at)
- `OtpService` (generate, hash, verify, consume, rate-limit enforcement)
- Rate limiting: 3 sends per 10min per mobile, 10 per hour per IP
- Brute force: 5 attempts per OTP, then 15-min lockout
- SMS provider adapter interface
- SMS provider: MIM SMS adapter (or sandbox adapter for dev)
- SMS provider: Log adapter (always available, for dev/CI)
- SMS provider failover logic (primary -> secondary on failure)
- `POST /api/v1/auth/otp/send` endpoint
- `POST /api/v1/auth/otp/verify` endpoint
- Email infrastructure: Laravel Mailable with queue, log driver for dev
- `POST /api/v1/auth/email/send-verification` endpoint
- OTP security tests: rate limit, brute force, expiry, consumed-once, value-not-logged

---

## WAVE 2 — Universal Identity & Registration Engine
**Dependencies:** Wave 1A + Wave 1B + Wave 1C (ALL THREE required)
**Invariants enforced:** I10, I11, I12, I15, I16, I23, I24

### Scope
- `users` table migration (global, no tenant_id — USR-2026-XXXXXXXX ID)
- `user_profiles`, `user_addresses`, `user_contacts` migrations
- `user_personal_kyc`, `membership_bank_accounts`, `membership_mfs_accounts` migrations
- `user_documents`, `user_contract_acceptances` migrations
- `employment_details` migration (tenant-scoped)
- `GlobalUserIdGenerator` (USR-{YEAR}-{8CHAR})
- `PositionReferenceNumberGenerator` ({CODE}-{YEAR}-{8CHAR})
- Registration Step 1 API flow (mobile OTP -> USR-ID -> photo/NID via W1B -> email)
- Registration Step 2 Wizard (profile sections, per-role fields)
- Profile completion engine (requirement matrices, not hardcoded percentages)
- KYC state machine (UNVERIFIED -> PENDING -> OCR_EXTRACTED -> PORICHOY_VERIFIED)
- NID OCR adapter (disabled/sandbox without credentials)
- Porichoy adapter (disabled/sandbox without credentials)
- MFA TOTP enrollment (QR generation, verification, recovery codes)
- Device tracking (`user_devices` table)
- Security event logging
- Tenant membership creation (on invitation accept / new tenant creation)
- Active tenant session management
- Registration tests (complete suite)
- Tenant isolation tests (global user data vs tenant data separation)
- Security tests (OTP value not logged, rate limit, brute force)
- KYC status tests (never fabricate PORICHOY_VERIFIED)

---

## WAVE 3 — Full Company/Branch/Capability/Subscription Engine
**Dependencies:** Wave 2
**Invariants enforced:** I01, I02, I18

### Scope
- `companies`, `branches`, `departments`, `warehouses` migrations
- Business type seeder (all industry types)
- Capability seeder (all capability definitions)
- `business_type_capabilities` pivot seeder
- Full `CapabilityResolutionService` with all resolution layers
- `RequiresCapability` middleware
- Feature flag system
- Module registry infrastructure
- Subscription plan model
- Tenant entitlement model
- Tenant settings (company name, logo, contact, currency, invoice notes)
- Custom domain infrastructure (DNS verification workflow)
- Tenant onboarding wizard (business type selection -> capability preview)
- Full isolation test suite

---

## WAVE 4 — Universal Product/Service/Asset Engine
**Dependencies:** Wave 3 + Wave 1B (Media for product images)

(Previously Wave 5. Renumbered due to W1 split.)

### Scope
- `commercial_items` migration (core + type extensions)
- Categories, brands, units, tax classes
- Pricing tiers (cost, wholesale, retail, MRP, DP, TP)
- Variant system
- Barcode / SKU generation
- Product images via Media Engine (W1B)
- Product CRUD API
- Product form (capability-driven: batch/serial/IMEI/expiry per capability)
- Product search (MySQL FTS)
- CSV/XLSX import (async job)

---

## WAVE 5 — Purchase / Sales / POS / Invoicing
**Dependencies:** Wave 4
(Previously Wave 6)

### Scope
- `inventory_movements` (canonical stock ledger)
- `InventoryMovementService` (canonical — I06)
- Purchase Order workflow
- Goods Receipt
- Sales Order
- Invoice generation
- Tax allocation (VAT in liability)
- POS module (add-on: pos)
- Payment allocation engine
- Returns / credit notes
- Multi-warehouse stock awareness

---

## WAVE 6 — Accounting / Finance / Ledger
**Dependencies:** Wave 5
(Previously Wave 7)

### Scope
- `ledger_entries` (immutable double-entry)
- `LedgerEngine` (canonical — I17)
- Chart of Accounts
- Journal Entry API
- General Ledger / Trial Balance / P&L / Balance Sheet
- Cash/Bank accounts
- Expense management
- Cheque management
- Receivables/Payables
- Tax rate management
- Full financial invariant test suite

---

## WAVE 7 — Wallet / FinTech / Payment Adapters
**Dependencies:** Wave 6
(Previously Wave 8)

### Scope
- `wallets`, `wallet_balances` (derived from ledger)
- WalletEngine (idempotent, pessimistic locking, ledger-derived balance)
- PaymentProviderAdapterLayer
- bKash adapter (sandbox)
- Nagad adapter (sandbox)
- SSLCommerz adapter (sandbox)
- Webhook infrastructure + signature verification
- Provider reconciliation
- Payment intent pinning (I25)

---

## WAVE 8 — CRM / Distribution / Commission
**Dependencies:** Wave 7
(Previously Wave 9)

---

## WAVE 9 — HR / Payroll / Corporate Hierarchy
**Dependencies:** Wave 8
(Previously Wave 10)

---

## WAVE 10 — Factory / Production
**Dependencies:** Wave 9
(Previously Wave 11)

---

## WAVE 11 — Warranty & Service Center
**Dependencies:** Wave 5
(Previously Wave 12)

---

## WAVE 12 — Ecommerce / Marketplace / Homepage Builder
**Dependencies:** Wave 4 + Wave 5
(Previously Wave 13)

---

## WAVE 13 — Delivery / Courier
**Dependencies:** Wave 5 + Wave 12
(Previously Wave 14)

---

## WAVE 14 — Advanced Communication Gateway
**Dependencies:** Wave 1C
(Previously Wave 15. Note: Basic OTP/email was already established in W1C)

### Scope (ADVANCED features beyond W1C foundation)
- WhatsApp provider adapter
- Push notification infrastructure
- Billing reminder workflows
- Delivery update notifications
- Tenant SMS/SMTP/WhatsApp configuration UI
- Notification template management (en/bn)
- Notification history and delivery receipts

---

## WAVE 15 — Tax / VAT Compliance & E-Trade License Add-ons
**Dependencies:** Wave 6
(Previously Wave 16)

---

## WAVE 16 — RAISA AI Layer
**Dependencies:** Wave 1B + Wave 14 (media + comms)
(Previously Wave 17)

---

## WAVE 17 — Security Operations Center
**Dependencies:** Wave 2
(Previously Wave 18)

---

## WAVE 18 — Reports & Analytics
**Dependencies:** Waves 5-14
(Previously Wave 19)

---

## WAVE 19 — Backup & Disaster Recovery
**Dependencies:** Wave 1
(Previously Wave 20)

---

## WAVE 20 — Production Hardening & Release Certification
**Dependencies:** All previous waves
(Previously Wave 21)

---

## Critical Path Summary

```
W0
  -> W1 (Foundation)
    -> W1A (Tenant + RBAC)    <-- W1B and W1C also depend on W1A
    -> W1B (Media Gateway)    <-- MUST exist before W2
    -> W1C (OTP/Comms Core)   <-- MUST exist before W2
      -> W2 (Identity/Registration) [requires W1A + W1B + W1C]
        -> W3 (Company/Branch/Capability)
          -> W4 (Products/Assets)
            -> W5 (Purchase/Sales/POS)
              -> W6 (Accounting/Ledger)
                -> W7 (Wallet/FinTech)
                  -> W8 (CRM/Distribution)
                    -> W9 (HR/Payroll)
                      -> W10 (Manufacturing)
              -> W11 (Warranty) [from W5]
              -> W12 (Ecommerce) [from W4+W5]
                -> W13 (Courier) [from W5+W12]
W1C -> W14 (Advanced Comms)
W6  -> W15 (Tax/Compliance)
W1B + W14 -> W16 (RAISA AI)
W2  -> W17 (Security SOC)
W5-W14 -> W18 (Reports)
W1  -> W19 (Backup/DR)
ALL -> W20 (Production Hardening)
```

---

## Wave Certification Reminder

No wave begins without the previous prerequisite wave(s) CERTIFIED.
No wave is certified without all gates in CERTIFICATION_GATES.md passing.

---

*Document Owner: Principal Architect | v1.1.0*


