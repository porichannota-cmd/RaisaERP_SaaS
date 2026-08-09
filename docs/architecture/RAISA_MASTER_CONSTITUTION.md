# RAISA ERP ENTERPRISE OS — MASTER CONSTITUTION
**Version:** 1.2.0
**Date:** 2026-08-09
**Status:** RATIFIED — Phase 00B (Final Architecture Freeze)
**Classification:** INTERNAL — ARCHITECTURE

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Phase 00 initial ratification |
| 1.1.0 | 2026-08-09 | Phase 00A: identity/tenancy, money, RBAC, payment routing, module security, MySQL isolation |
| 1.2.0 | 2026-08-09 | Phase 00B: canonical BIGINT money, scoped grants, global/membership data separation, multi-context tenant resolvers, queue isolation, effective-dated position history, cache safety, domain event safety, audit actor model |

---

## Preamble

RAISA ERP Enterprise OS is a production-grade, modular, multi-tenant, multi-company
Enterprise Operating System. It is not a demo, prototype, or single-industry tool.

This Constitution is the supreme governing document. Where conflict exists between
any implementation detail and this Constitution, this Constitution prevails.

---

## Article I — Non-Negotiable Invariants

| Code | Invariant |
|------|-----------|
| I01 | Tenant isolation cannot be bypassed at any layer. |
| I02 | Client-supplied tenant scope is NEVER trusted as authorization. |
| I03 | Financial mutations are idempotent. |
| I04 | Financial ledger history is not silently rewritten. Corrections via reversal only. |
| I05 | Money amounts use BIGINT integer minor units for computation and storage. NEVER float. DECIMAL(20,10) for rates only. |
| I06 | Stock changes occur ONLY through the canonical Inventory Movement Service. |
| I07 | User-uploaded bytes cannot become trusted/public before server-side validation completes. |
| I08 | Private identity/legal documents NEVER use permanent public URLs. |
| I09 | Secrets NEVER enter frontend bundles, logs, or API responses after initial save. |
| I10 | OTP values, passwords, PINs, and provider secrets are NEVER logged. |
| I11 | External provider success is NEVER fabricated. |
| I12 | Government authority or integration is NEVER fabricated. |
| I13 | Regulatory access is explicitly scoped, purpose-bound, and audited. |
| I14 | Exact user location is NEVER covertly collected. |
| I15 | ONE canonical Registration Engine. No duplicates. |
| I16 | ONE canonical Media Engine. No duplicates. No temporary uploaders. |
| I17 | ONE canonical Ledger Engine. No duplicates. |
| I18 | ONE canonical Tenant Context resolver per execution context. No duplicates. |
| I19 | Certified engines cannot be bypassed by new modules or add-ons. |
| I20 | Add-ons must fail safely without breaking core ERP. |
| I21 | Every privileged mutation creates appropriate, immutable audit evidence. |
| I22 | No production wave is certified solely because it compiles. |
| I23 | A human user identity is GLOBALLY unique and may belong to MULTIPLE tenants. |
| I24 | Global user identity (USR-YYYY-XXXXXXXX) is IMMUTABLE. Role/position codes are NOT identity. |
| I25 | A payment intent is PINNED to its originating provider after creation. No silent provider migration of an existing intent. |
| I26 | Frontend capability checks are PRESENTATION ONLY. Every protected resource requires server-side Policy/Gate enforcement. |
| I27 | Module installation is a platform-controlled deployment. Tenants activate; they do NOT upload or execute code. |
| I28 | The canonical money unit is BIGINT integer minor units (e.g., paisa for BDT). DECIMAL is used ONLY for rates. FLOAT/DOUBLE are FORBIDDEN everywhere. |
| I29 | Authorization grants are ALWAYS paired with scope. Permissions and scopes are NOT independently unioned. A broad scope from one role NEVER widens a sensitive permission from another role. |
| I30 | Data that describes a human being is globally owned. Data that describes a business relationship with a tenant is membership-owned. They are NEVER mixed in the same table. |
| I31 | Every tenant-scoped queue job MUST carry tenant_id, actor_id, and correlation_id in its payload. Context is established before domain code and cleared in finally{}. |
| I32 | Tenant context MUST NOT leak between queue jobs. Context is established per-job and cleared after completion, even on failure. |
| I33 | Every tenant-scoped domain event MUST contain tenant_id and correlation_id in its payload. Listeners MUST NOT infer tenant from ambient state. |
| I34 | Any cache key derived from tenant data MUST include tenant identity. Shared keys are FORBIDDEN for tenant-specific data. |
| I35 | Position assignment history is NEVER mutated. Promotion/transfer creates a new assignment (new reference number, new effective_from). Old assignment is closed with effective_to date. |

---

## Article II — Identity Model (v1.1.0 — unchanged)

A user is a GLOBALLY unique human account with NO tenant_id on the users table.
See MULTI_TENANCY.md §2 for schema.

### 2.1 Global / Membership Data Separation (NEW v1.2.0)

GLOBAL personal data (owned by the user, not tenant-specific):
  users, user_personal_profiles, user_personal_contacts,
  user_personal_addresses, user_personal_kyc, user_personal_documents

MEMBERSHIP data (owned by the tenant-membership relationship):
  tenant_memberships, membership_profiles, membership_addresses,
  membership_bank_accounts, membership_mfs_accounts, membership_documents,
  membership_contracts, membership_employment, membership_business_profiles

COMPANY / LEGAL ENTITY data (owned by the company, not the user):
  companies, company_licenses   — TIN, BIN, Trade License, VAT registration

(Invariant I30) See DATA_OWNERSHIP.md.

---

## Article III — Platform Hierarchy (unchanged)

```
Platform (RAISA HQ) → Tenants → Tenant Members
  → Companies/Legal Entities → Branches → Departments/Warehouses
    → Position Assignments / Teams
```

---

## Article IV — Module System (v1.1.0 — unchanged)

Platform deploys. Tenants activate. (Invariant I27)
See MODULE_ADDON_SYSTEM.md.

---

## Article V — Financial Integrity (REVISED v1.2.0)

### 5.1 Canonical Money Model

```
COMPUTATION + STORAGE:  BIGINT SIGNED (integer minor units)
                        BDT 1234.50 → 123450 paisa
                        USD 10.25   → 1025 cents
                        JPY 500     → 500 yen (exponent=0)

RATES/PERCENTAGES:      DECIMAL(20,10) — FX rates, tax rates, interest rates
                        NEVER for transactional amounts

FLOAT/DOUBLE:           FORBIDDEN everywhere (I28)

JSON contract:          { "amount_minor": "123450", "currency": "BDT", "formatted": "৳1,234.50" }
                        amount_minor is STRING in JSON to prevent float loss

ROUNDING:               HALF_UP per currency scale (from ISO 4217 minor_unit_exp)
                        Applied ONCE at final output per calculation chain
                        Tax: round HALF_UP per line item, then sum
```

See MONEY_MODEL.md for full specification.

### 5.2 Ledger Architecture (unchanged)

Double-entry. Immutable. BIGINT minor units. Idempotency key required.

### 5.3 Payment Routing (v1.1.0 — unchanged)

Provider pinned at intent creation. (Invariant I25) See PROVIDER_ADAPTERS.md.

---

## Article VI — Authorization (REVISED v1.2.0)

### 6.1 Scoped Grant Model (NEW v1.2.0)

Authorization grants are ATOMIC TRIPLES: permission + scope_type + scope_id.
NOT independently unioned. (Invariant I29)

```sql
authorization_grants (permission_key, scope_type, scope_id, constraints, expires_at)
```

Scope types: TENANT > COMPANY > BRANCH > WAREHOUSE > DEPARTMENT > TERRITORY > OWN

A broad scope from one role NEVER widens a sensitive permission from a different role.
Each grant is evaluated independently. No scope bleed between grants.

### 6.2 Multi-Role Composition

When a user holds multiple grants, authorization checks are against INDIVIDUAL grants.
A resource access is authorized only if a SINGLE grant covers both permission AND resource scope.

### 6.3 Backend is ALWAYS Authoritative (I26 — unchanged)

Frontend capability checks = UX only. See BUSINESS_CAPABILITY_ENGINE.md.

---

## Article VII — Media & File Security (unchanged — v1.1.0)

Canonical Media Engine. No bypass. Must exist before Registration. See MEDIA_SECURITY.md.

---

## Article VIII — OTP / Communication (unchanged — v1.1.0)

OTP infrastructure before Registration. See SECURITY_MODEL.md.

---

## Article IX — Payment Provider Routing (unchanged — v1.1.0)

Intent pinned at creation. (I25) See PROVIDER_ADAPTERS.md.

---

## Article X — Health Endpoints (unchanged — v1.1.0)

GET /health/live    — Public liveness. Minimal response.
GET /health/ready   — Public readiness. Minimal response.
GET /health/detail  — Privileged. Internal only. NEVER public.

---

## Article XI — Font Delivery (unchanged — v1.1.0)

Self-hosted WOFF2. No runtime Google Fonts dependency.

---

## Article XII — Privacy & Data Classification (unchanged — v1.1.0)

RESTRICTED data (NID, PIN, secrets): at-rest + field + transit encryption.

---

## Article XIII — MySQL Tenant Isolation Defense (unchanged — v1.1.0)

7-layer defense. All layers mandatory. Not equivalent to DB RLS.

---

## Article XIV — Tenant Context Resolvers (NEW v1.2.0)

TenantContext MUST work for:

| Source | Resolver |
|--------|---------|
| Authenticated web session | WebSessionTenantResolver |
| Sanctum API token / mobile | ApiTokenTenantResolver |
| Service principal | ServicePrincipalTenantResolver |
| Inbound webhook | WebhookTenantResolver |
| Queue job | QueueJobTenantResolver |
| Scheduled task | ScheduledTaskTenantResolver |
| CLI / Artisan maintenance | CliTenantResolver |
| SA cross-tenant access | SuperAdminCrossTenantResolver (explicit + audited) |

ONE canonical TenantContextManager. No ad-hoc resolution. (I18)
Context established from resolver payload — never from ambient state alone.
Context always cleared in `finally {}`.

See TENANT_CONTEXT_RESOLVERS.md.

---

## Article XV — Queue Tenant Safety (NEW v1.2.0)

Every tenant-scoped job MUST carry in payload:
  tenant_id, actor_id (where applicable), correlation_id, request_id

Context established before domain code. Cleared in finally{}.
Cross-tenant queue isolation tests mandatory per wave. (I31, I32)

See QUEUE_TENANT_SAFETY.md.

---

## Article XVI — Effective-Dated Position History (NEW v1.2.0)

Historical position assignments MUST NOT be mutated.
Promotion/transfer: close old assignment (effective_to) + create new (new reference_number).
Point-in-time resolution: getPositionAtTime(userId, tenantId, timestamp).
Historical documents snapshot position reference at time of creation. (I35)

See POSITION_HISTORY.md.

---

## Article XVII — Cache Tenant Safety (NEW v1.2.0)

All tenant-derived cache keys include tenant_id.
Format: tenant:{tenantId}:{resource}:{qualifier}
Shared keys for tenant-specific data: FORBIDDEN.
Invalidation mandatory on: permission change, capability change, membership revocation,
tenant suspension, subscription change. (I34)

See CACHE_SAFETY.md.

---

## Article XVIII — Domain Event Tenant Safety (NEW v1.2.0)

Every tenant-scoped domain event carries tenant_id + correlation_id in payload.
Listeners extract tenant from event payload — NOT from ambient state. (I33)
Outbox records include tenant_id and correlation_id.

See DOMAIN_EVENTS.md.

---

## Article XIX — Audit Actor Model (NEW v1.2.0)

Actor types: USER, PLATFORM_ADMIN, SYSTEM, QUEUE_WORKER, SCHEDULED_JOB, API_CLIENT, WEBHOOK.
Every privileged mutation records: tenant_id, actor_type, actor_id, impersonator_id,
correlation_id, request_id, IP/user-agent (interactive). (I21)
Secrets NEVER logged. Audit log is IMMUTABLE.

See AUDIT_MODEL.md.

---

## Article XX — Certification Gates (v1.1.0 — unchanged)

All 9 gates must pass before any wave ships.

---

## Article XXI — Amendment Process

1. Architect proposes with rationale and impact.
2. Security and QA review.
3. Written authorization from Principal Architect.
4. Constitution updated with version bump + change log.
5. All affected waves re-reviewed.

---

*Version 1.2.0 — RATIFIED: 2026-08-09 Phase 00B Final Architecture Freeze*
