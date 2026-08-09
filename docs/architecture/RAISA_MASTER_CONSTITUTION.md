# RAISA ERP ENTERPRISE OS — MASTER CONSTITUTION
**Version:** 1.1.0
**Date:** 2026-08-09
**Status:** RATIFIED — Phase 00A (Contradiction Closure)
**Classification:** INTERNAL — ARCHITECTURE

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Phase 00 initial ratification |
| 1.1.0 | 2026-08-09 | Phase 00A: Fix identity/tenancy model, money model, RBAC scopes, capability enforcement, payment routing, module security, MySQL isolation |

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
| I05 | Money amounts use integer minor units (paisa) for computation. Storage: DECIMAL(20,6). NEVER float. |
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
| I18 | ONE canonical Tenant Context resolver. No duplicates. |
| I19 | Certified engines cannot be bypassed by new modules or add-ons. |
| I20 | Add-ons must fail safely without breaking core ERP. |
| I21 | Every privileged mutation creates appropriate, immutable audit evidence. |
| I22 | No production wave is certified solely because it compiles. |
| I23 | A human user identity is GLOBALLY unique and may belong to MULTIPLE tenants. |
| I24 | Global user identity (USR-YYYY-XXXXXXXX) is IMMUTABLE. Role/position codes are NOT identity. |
| I25 | A payment intent is PINNED to its originating provider after creation. No silent provider migration of an existing intent. |
| I26 | Frontend capability checks are PRESENTATION ONLY. Every protected resource requires server-side Policy/Gate enforcement. |
| I27 | Module installation is a platform-controlled deployment. Tenants activate; they do NOT upload or execute code. |

---

## Article II — Identity Model (REVISED v1.1.0)

### 2.1 Global User Identity

A user is a GLOBALLY unique human account, NOT a tenant-scoped record.

```
users (global, NOT tenant-scoped)
  id              CHAR(26) ULID — primary key
  global_user_id  VARCHAR(20) UNIQUE — format: USR-YYYY-XXXXXXXX (immutable)
  mobile          VARCHAR(20) UNIQUE — primary identity
  mobile_verified BOOLEAN
  email           VARCHAR(255) UNIQUE NULL
  email_verified  BOOLEAN
  password_hash   VARCHAR(255) NULL    — bcrypt, nullable (OTP-only login possible)
  status          ENUM(active, suspended, banned, pending_verification)
  mfa_secret      VARCHAR(255) NULL    — encrypted TOTP secret
  mfa_enabled     BOOLEAN DEFAULT FALSE
  created_at, updated_at, deleted_at  — soft delete only for banned/GDPR
```

### 2.2 Tenant Membership

A user gains access to a tenant through an explicit membership record.

```
tenant_memberships
  id              CHAR(26) ULID
  user_id         CHAR(26) FK -> users.id  (global)
  tenant_id       CHAR(26) FK -> tenants.id
  status          ENUM(active, suspended, invited, revoked)
  invited_by      CHAR(26) NULL FK -> users.id
  joined_at       TIMESTAMP NULL
  suspended_at    TIMESTAMP NULL
  suspension_reason TEXT NULL
  created_at, updated_at
  UNIQUE (user_id, tenant_id)

tenant_membership_roles
  id              CHAR(26) ULID
  membership_id   CHAR(26) FK -> tenant_memberships.id
  role_key        VARCHAR(100)          — e.g., 'tenant_admin', 'sales_manager'
  company_id      CHAR(26) NULL FK -> companies.id   — NULL = tenant-wide
  branch_id       CHAR(26) NULL FK -> branches.id    — NULL = company-wide
  department_id   CHAR(26) NULL FK -> departments.id — NULL = branch-wide
  granted_at      TIMESTAMP
  granted_by      CHAR(26) FK -> users.id
  expires_at      TIMESTAMP NULL
  created_at, updated_at
```

### 2.3 Active Tenant Context

A user may belong to multiple tenants. The active tenant is resolved server-side.

```
active_tenant_sessions
  id              CHAR(26) ULID
  user_id         CHAR(26) FK -> users.id
  session_id      VARCHAR(100) — FK to sessions table
  tenant_id       CHAR(26) FK -> tenants.id
  activated_at    TIMESTAMP
  -- No client-supplied tenant_id is ever trusted for this
```

Tenant switching flow:
1. User authenticated → list available memberships (active ones only)
2. User selects tenant → POST /api/v1/auth/tenant/switch {tenant_id}
3. Server verifies: membership exists AND is active AND tenant is active
4. Server updates active_tenant_sessions record
5. Server re-derives capability set for new tenant context
6. All subsequent requests read tenant from server-side session context

### 2.4 Tenant Context Resolution (REVISED)

```
HTTP Request
  -> Middleware: ResolveTenantContext
    -> Read session_id from authenticated session
    -> Look up active_tenant_sessions (session_id)
    -> Load tenant membership for user + tenant
    -> Verify: membership.status == active AND tenant.status == active
    -> Derive: roles, permissions, capabilities for this context
    -> Bind to request context: tenant, tenant_id, membership, permissions
    -> NEVER read tenant_id from request body or query string
```

### 2.5 Position / Role Codes (NOT Identity)

Role and position codes such as TA-2026-..., DIR-FIN-2026-..., DD-2026-...
are DISPLAY LABELS or REFERENCE CODES within a tenant's system.
They are NOT the user's global identity. They may change.

```
position_assignments
  id              CHAR(26) ULID
  user_id         CHAR(26) FK -> users.id (global)
  tenant_id       CHAR(26) FK -> tenants.id
  position_code   VARCHAR(50)   — e.g., 'TA', 'DIR-FIN', 'DD', 'CUST'
  reference_number VARCHAR(30)  — e.g., 'TA-2026-Q8M7R2P4' (display only)
  effective_from  DATE
  effective_to    DATE NULL
  created_at, updated_at
```

---

## Article III — Platform Hierarchy

```
Platform (RAISA HQ)
  Super Admin (SA)
      Tenants
          Tenant Members (users with active membership)
          Companies / Legal Entities
              Branches
                  Departments / Warehouses / Facilities
                      Position Assignments / Teams
```

---

## Article IV — Module System

### 4.1 Core vs. Add-on Boundary

Platform deploys modules. Tenants ACTIVATE deployed modules.
Tenants NEVER upload or execute PHP code. (Invariant I27)

Module installation = platform-controlled deployment (CI/CD).
Module entitlement = per-tenant activation flag in the database.

### 4.2 Module Contract (PHP Interface)

Every module must implement: key, name, version, dependencies, migrations,
permissions, routes, menu, settings, install, enable, disable, upgrade, healthStatus.

### 4.3 Capability Resolution Chain

BusinessType -> BusinessTypeCapability -> TenantCapabilityOverride
  -> FeatureFlag -> ModuleEntitlement -> CapabilitySet

Frontend receives capability set via Inertia shared data.
PRESENTATION ONLY. (Invariant I26)
EVERY capability-gated action requires server-side Policy/Gate. (Invariant I26)

---

## Article V — Financial Integrity

### 5.1 Canonical Money Model (REVISED v1.1.0)

ONE canonical money model. No mixing.

COMPUTATION:   Integer minor units (paisa for BDT). No floating point arithmetic.
STORAGE:       DECIMAL(20,6) in MySQL — never FLOAT, never DOUBLE.
PRECISION:     High-precision DECIMAL(20,10) value objects for FX rates, tax rates.
ROUNDING:      HALF_UP (round 0.5 away from zero) — applied once at final output only.
CURRENCY SCALE: BDT = 2 decimal places (paisa). USD = 2. JPY = 0. Always from ISO 4217.
SERIALIZATION: JSON API: string representation ("1234.50"), NEVER numeric float.
OVERFLOW:      DECIMAL(20,6) supports 14 integer digits — sufficient for any realistic amount.
TAX ROUNDING:  Per-line item rounded HALF_UP, then summed (not sum-then-round).
INVOICE ROUND: Final invoice total rounded to currency scale HALF_UP.

### 5.2 Ledger Architecture

Double-entry. Immutable. DECIMAL(20,6). Idempotency key required.
Corrections via reversal entries only. (Invariant I04)

### 5.3 Idempotency

All financial mutations require idempotency_key.
Same key → original result, no re-execution.

---

## Article VI — Authorization (REVISED v1.1.0)

### 6.1 Scope Hierarchy

Authorization must be verified at the narrowest applicable scope:

Platform > Tenant > Company > Branch > Warehouse > Department > Territory > Own-record

### 6.2 Permission Resolution

```
EffectivePermissions = UNION of all roles held by user
  in the matching membership for current active tenant context
  filtered by scope constraints (company, branch, department, territory)
```

Roles are additive (union). No role silently overrides another.

### 6.3 Multi-Role Rules

A user may hold multiple roles within one tenant.
Permissions from all active roles are unioned.
Scope constraints are intersected (narrowest scope wins for restricted operations).

### 6.4 Backend is Authoritative

Frontend capability/permission checks are UX guidance only.
EVERY protected API endpoint, EVERY policy, EVERY service method enforces
permissions server-side. (Invariant I26)

---

## Article VII — Media & File Security

The canonical Media Engine is the ONLY path for file ingestion. (Invariant I16)
No temporary uploaders. No ad-hoc upload routes.

Media Engine must exist (Wave 1B) BEFORE Registration Engine (Wave 2).

---

## Article VIII — OTP / Communication

OTP infrastructure must exist (Wave 1C) BEFORE Registration Engine (Wave 2).
No temporary OTP implementation allowed as a workaround. (Invariant I15, I16)

---

## Article IX — Payment Provider Routing (REVISED v1.1.0)

### 9.1 Provider Selection Before Intent

Payment provider is selected and pinned BEFORE a payment intent is created.
Provider selection uses: tenant configuration, provider priority, health status.

### 9.2 Intent Pinning

Once a payment intent is created with a provider, that intent is PINNED to that provider.
(Invariant I25)

### 9.3 Failed Intent Recovery

If a provider fails AFTER intent creation:
- Mark intent status = PROVIDER_FAILED
- DO NOT silently switch provider for this intent
- Create a NEW idempotent payment intent with the next provider
- Audit log the failure and the new intent creation

### 9.4 SMS/Email Failover

SMS and email providers (non-financial) may use transparent policy-driven failover.
This does NOT apply to payment providers.

---

## Article X — Health Endpoints (REVISED v1.1.0)

```
GET /health/live    — Liveness: Is the process running?
                      Public. Returns: {"status":"ok"} or {"status":"down"}
                      Minimal info. No dependency details.

GET /health/ready   — Readiness: Is the service ready to accept traffic?
                      Public. Returns: {"status":"ready"} or {"status":"not_ready", "reason":"..."}
                      Minimal info. No secrets. No internal paths.

GET /health/detail  — Detailed dependency health.
                      REQUIRES: internal network access or privileged API token.
                      Returns: DB, Redis, queue, storage status + latency.
                      NEVER exposed publicly.
```

---

## Article XI — Font Delivery (REVISED v1.1.0)

Fonts must NOT be fetched from Google Fonts at runtime in production.

Self-hosted strategy (subject to license verification):
- Inter: SIL Open Font License — self-hosting permitted.
- Hind Siliguri: SIL Open Font License — self-hosting permitted.
- Fonts bundled in `public/fonts/` or via `npm` package.
- WOFF2 format with appropriate `font-display: swap`.
- Subset to required Unicode ranges (Latin + Bengali Lipi block).

---

## Article XII — Privacy & Data Classification

| Class | Examples | Encryption | Location |
|-------|----------|-----------|---------|
| PUBLIC | Catalog, blog | Transit | CDN |
| INTERNAL | Reports | Transit | App |
| CONFIDENTIAL | Contracts, HR | At-rest + Transit | App/DB |
| RESTRICTED | NID, PIN, secrets | At-rest + Field + Transit | DB encrypted |

Location data: Explicit permission + declared purpose + audit trail required.

---

## Article XIII — MySQL Tenant Isolation Defense (NEW v1.1.0)

MySQL does not provide PostgreSQL-style Row Level Security (RLS).
Application-level defense in depth is MANDATORY at all layers:

1. Server-resolved TenantContext (Invariant I18)
2. Global TenantScope query scope on all tenant models
3. Policy-level tenant_id verification on all resource operations
4. Domain service-level TenantContext assertion
5. Scoped repository queries with explicit tenant_id filters
6. Composite unique constraints including tenant_id where appropriate
7. Foreign-key ownership validation in policies (not just key existence)
8. Cross-tenant negative tests required for every new tenant-scoped resource

Application scopes are NOT equivalent to DB-level RLS.
They are complementary layers. All layers must be present.

---

## Article XIV — Certification Gates

No wave ships to production without ALL gates:
1. Architecture review — no invariant violations
2. Unit tests — pass
3. Feature/API tests — pass
4. Tenant isolation tests — pass (including cross-tenant negative tests)
5. Authorization tests — pass
6. Financial invariant tests — pass (where applicable)
7. Security review — pass
8. Performance baseline — meets budget
9. Regression suite — pass (no certified engine broken)

---

## Article XV — Amendment Process

1. Architect proposes amendment with rationale and impact assessment.
2. Security and QA review.
3. Written authorization from Principal Architect.
4. Constitution updated with version bump and change log.
5. All affected waves re-reviewed.

---

*Version 1.1.0 — RATIFIED: 2026-08-09 Phase 00A*
