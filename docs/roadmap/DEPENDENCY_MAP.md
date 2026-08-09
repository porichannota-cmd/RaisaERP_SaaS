# RAISA ERP — MODULE DEPENDENCY MAP
**Version:** 1.2.0 | **Date:** 2026-08-09 | **Phase:** 00B

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | W1 split into W1/W1A/W1B/W1C, global user identity model
| 1.2.0 | 2026-08-09 | BIGINT money, scoped grants, global/membership data separation, 8 new architecture documents |

---

## Platform Core Foundations

```
[Platform Code Stack]
  Laravel 12.x / PHP 8.3+
  MySQL 8.x / Redis 7.x
  Inertia.js 2.x / React 19.x / TypeScript 5.7+
  Tailwind CSS 4.x / Radix UI / Lucide React / Vite 6.x

[Wave 1 — Foundation]
  Money VO (bcmath, integer minor units, DECIMAL storage)
  BEFDS Design System (CSS tokens, self-hosted fonts)
  App shell (sidebar, layout)
  i18n (en/bn)

[Wave 1A — Tenant + RBAC Primitives]
  Tenant model, TenantContext middleware
  Global TenantScope query scope
  Spatie RBAC: roles, permissions
  tenant_memberships, tenant_membership_roles
  active_tenant_sessions, position_assignments

[Wave 1B — Certified Media Gateway Core]
  media_uploads table
  Upload Preflight API (signed URL)
  ProcessMediaUploadJob (security validation pipeline)
  Signed Delivery URL API
  No bypass path exists (I16)

[Wave 1C — OTP / Communication Core]
  otp_records table
  OtpService (generate, hash, verify, rate-limit)
  SMS Provider Adapter (MIM SMS + sandbox)
  Email infrastructure (queue-based, log driver)
  /api/v1/auth/otp/send, /verify endpoints
```

---

## Identity Dependency Graph

```
Wave 1A + Wave 1B + Wave 1C
  ALL three required
  -> Wave 2: Universal Identity & Registration Engine

Users (global, no tenant_id)
  -> user_profiles         (personal info)
  -> user_addresses        (type-tagged locations)
  -> user_contacts         (secondary mobiles, social)
  -> membership_bank_accounts (field-encrypted per membership)
  -> membership_mfs_accounts  (bKash, Nagad, etc. per membership)
  -> user_kyc_records      (NID, passport — via Media Engine)
  -> user_documents        (TIN, Trade License — via Media Engine)
  -> user_contract_acceptances (immutable legal audit)

tenant_memberships (users <-> tenants — many-to-many)
  -> tenant_membership_roles (scoped roles per membership)
  -> active_tenant_sessions  (server-resolved active context)
  -> position_assignments    (display codes, NOT identity)
  -> employment_details      (tenant-scoped employment data)
```

---

## Domain Dependency Graph

```
Global Identity (W2)
  -> Tenancy + Company (W3)
      -> Commerce / Products (W4)
      |     -> Inventory (W5)
      |           -> Purchase / Sales / POS (W5)
      |                 -> Accounting / GL (W6)
      |                       -> Wallet / FinTech (W7)
      |                             -> CRM / Distribution (W8)
      |                                   -> HR / Payroll (W9)
      |                                         -> Manufacturing (W10)
      |                 -> Warranty (W11) [from W5]
      |                 -> Ecommerce (W12) [from W4+W5]
      |                       -> Courier/Delivery (W13)
      |
      -> Accounting (W6)
            -> Tax/Compliance (W15)
      
OTP/Comms Core (W1C) -> Advanced Comms Gateway (W14)
Media Gateway (W1B) + Advanced Comms (W14) -> RAISA AI (W16)
Identity (W2) -> Security SOC (W17)
W5-W14 -> Reports/Analytics (W18)
W1 -> Backup/DR (W19)
ALL -> Production Hardening (W20)
```

---

## Provider Adapter Dependency Graph

```
SMS Provider (MIM SMS / Log adapter)
  Required by: W1C OTP — critical bootstrap dependency
  Optional advanced: Notification templates (W14)

Payment Provider (bKash, Nagad, SSLCommerz, ...)
  Required by: Wallet/FinTech (W7)
  Status: sandbox until authorized credentials

Courier Provider (Pathao, Steadfast, RedX, ...)
  Required by: Delivery (W13)
  Status: sandbox until authorized credentials

Identity Provider (Porichoy)
  Required by: KYC in Registration (W2)
  Status: DISABLED until legal authorization + credentials

AI Provider (OpenAI / compatible, ElevenLabs)
  Required by: RAISA AI Layer (W16)
  Status: DISABLED until authorized credentials

Email Provider (SMTP)
  Required by: W1C email infrastructure
  Status: log driver (dev), SMTP (production)

Object Storage (S3-compatible)
  Required by: W1B Media Gateway
  Status: local disk (dev), S3 (production)

CDN
  Required by: public media delivery
  Status: local (dev), CloudFront/Bunny (production)
```

---

## Module Enable/Disable Dependencies (unchanged from v1.0.0)

```
pos         depends-on: commerce.products, commerce.sales
pharmacy    depends-on: commerce.products + batch_tracking capability
restaurant  depends-on: commerce.products (food type), pos
hotel       depends-on: commerce.items (room type), crm.customers
warranty    depends-on: commerce.products (serial tracking), crm.customers, commerce.sales
ecommerce   depends-on: commerce.products, commerce.pricing, payment (at least one)
distribution depends-on: crm.customers, finance.wallets, accounting.commission
raisa_ai_voice  depends-on: raisa_ai_core
raisa_ai_ocr    depends-on: raisa_ai_core, media (W1B)
trade_license   depends-on: identity.kyc (W2), tenancy.company (W3)
```

---

## Minimum Viable Product (MVP) Critical Path

```
W1 -> W1A -> W1B -> W1C
  -> W2 -> W3 -> W4 -> W5 -> W6
```

This 7-step sequence (W1 + W1A + W1B + W1C + W2 + W3 + W4 + W5 + W6)
delivers a minimum viable ERP for a basic retail business with:
- Global identity + multi-tenant memberships
- Certified media engine
- OTP authentication
- Product catalogue
- Purchase + Sales + POS
- Full double-entry accounting

---

---

## New Documents Added in Phase 00B

| Document | Purpose |
|----------|---------|
| MONEY_MODEL.md | Canonical BIGINT money specification |
| AUTHORIZATION_GRANTS.md | Scoped permission grant model (I29) |
| DATA_OWNERSHIP.md | Global vs membership data separation (I30) |
| TENANT_CONTEXT_RESOLVERS.md | Multi-source context resolvers (I18, I31) |
| QUEUE_TENANT_SAFETY.md | Job tenant safety (I31, I32) |
| DOMAIN_EVENTS.md | Event tenant safety + outbox (I33) |
| CACHE_SAFETY.md | Cache key safety (I34) |
| POSITION_HISTORY.md | Effective-dated position history (I35) |
| AUDIT_MODEL.md | Audit actor types and schema (I21) |

Total architecture documents: **32** (was 23 after Phase 00A)

---

*Document Owner: Principal Architect | v1.2.0*


