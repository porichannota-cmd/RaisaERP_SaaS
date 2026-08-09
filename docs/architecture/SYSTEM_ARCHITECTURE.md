# RAISA ERP — SYSTEM ARCHITECTURE
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00A

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | Global user identity (no tenant_id on users), health endpoint split (/live /ready /detail), font self-hosting, ADR updates |

---

## 1. Architectural Style

RAISA ERP uses a **Domain-Driven, Service-Oriented Monolith** (Modular Monolith) architecture.

### Why not microservices immediately?
- Team size and budget cannot sustain full microservices operational overhead at launch.
- The bounded domain model ensures clean internal separation that can be extracted later.
- Laravel's excellent service/domain layers enable clean internal boundaries without distributed system complexity.

### Architecture Decision: Modular Monolith -> Selective Microservice Extraction

```
[Modular Laravel Backend]
  Domains/
    Auth/
    Tenancy/
    Identity/
    Commerce/
    Inventory/
    Accounting/
    Finance/
    HR/
    CRM/
    Distribution/
    Ecommerce/
    Compliance/
    AI/
    Media/
    Notifications/
    Platform/

  [Shared Kernel]
    - TenantContext
    - LedgerEngine
    - MediaEngine
    - RegistrationEngine
    - EventBus
    - AuditEngine
```

---

## 2. Backend Architecture

### 2.1 Stack

| Component | Technology | Version Target |
|-----------|-----------|----------------|
| Runtime | PHP | 8.2+ (8.3 preferred) |
| Framework | Laravel | 12.x |
| API Layer | Inertia.js + REST API | v2.x |
| Queue | Laravel Queues + Redis | - |
| Cache | Redis | 7.x |
| Sessions | MySQL (database) | - |
| Search | MySQL FTS -> Meilisearch (future) | - |
| Jobs | Laravel Horizon (future, with Redis) | - |

### 2.2 Layer Map

```
HTTP Layer (Routes / Middleware)
  -> FormRequest (validation)
  -> Controller (thin - orchestration only)
    -> ApplicationService (use-case orchestration)
      -> DomainService (business logic)
        -> Repository (data access)
          -> Eloquent Model (ORM)
            -> MySQL 8.x
      -> Events / Listeners
      -> Jobs / Queues
      -> Notifications
```

### 2.3 Directory Structure (Target)

```
app/
  Domain/
    Auth/
      Actions/
      Contracts/
      Exceptions/
      Listeners/
      Models/
      Notifications/
      Policies/
      Services/
    Tenancy/
    Identity/
    Commerce/
    Inventory/
    Accounting/
    Finance/
    HR/
    CRM/
    Distribution/
    Media/
    Notifications/
    Platform/
  Http/
    Controllers/
    Middleware/
    Requests/
    Resources/   (API Resources)
  Models/        (Eloquent models - thin)
  Providers/
  Services/      (Cross-domain shared services)
  Support/       (Helpers, Macros, Traits)
```

### 2.4 API Architecture

- Primary: Inertia.js (server-driven SPA for ERP dashboard)
- Secondary: REST API v1 (for mobile apps, webhooks, external integrations)
- Versioning: URL prefix (/api/v1/...) for REST; Inertia handles SPA routing
- Rate limiting: per-user, per-tenant, per-endpoint tiers

### 2.5 Queue Architecture

```
Queue Connections:
  default     -> general background jobs
  media       -> media processing (slow, isolated)
  notifications -> SMS/email/push (fast, isolated)
  ledger      -> financial postings (critical, sequential where needed)
  exports     -> report/data exports (slow, low priority)
  ai          -> AI inference jobs (slow, bursty)

Workers:
  php artisan queue:work --queue=ledger,default,media,notifications,exports,ai
```

---

## 3. Frontend Architecture

### 3.1 Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| Framework | React | 19.x |
| Language | TypeScript (strict) | 5.7+ |
| Build Tool | Vite | 6.x |
| CSS | Tailwind CSS | 4.x |
| SPA Bridge | Inertia.js | 2.x |
| Components | Radix UI + BEFDS | custom |
| Icons | Lucide React | 0.475+ |
| State | React Context + useReducer | built-in |
| Forms | Custom hooks + Inertia useForm | - |
| Routing | Inertia + Ziggy | - |

### 3.2 Frontend Directory Structure (Target)

```
resources/js/
  app.tsx                 - Inertia entrypoint
  ssr.tsx                 - SSR entrypoint (TypeScript — NOT .jsx)
  components/
    ui/                   - BEFDS primitives (Button, Input, Card, etc.)
    shared/               - Shared compound components
    forms/                - Form field components
    layout/               - Layout components
    charts/               - Chart components
    media/                - Media upload/display components
  features/               - Feature-domain components
    auth/
    dashboard/
    identity/
    commerce/
    inventory/
    accounting/
    hr/
    crm/
  hooks/                  - Custom React hooks
  lib/                    - Utilities, formatters, validators
  pages/                  - Inertia page components
    auth/
    dashboard/
    settings/
    identity/
    commerce/
    inventory/
    accounting/
    ...
  stores/                 - Global state (Context)
  types/                  - TypeScript type definitions
  i18n/                   - Translation files (bn/en)
```

### 3.3 Code Splitting

- Route-level lazy loading for all page components
- Feature-domain chunks (commerce, accounting, hr etc.)
- Vendor chunk separation
- Critical above-fold CSS inlined

---

## 4. Infrastructure Architecture

### 4.1 Development (Current State)

```
[Windows Dev Machine]
  php artisan serve :8000  (Laravel)
  npm run dev              (Vite HMR)
  MySQL 8.x               (local)
  Redis                   (local, optional phase 1)
```

### 4.2 Production Target

```
[Nginx / Reverse Proxy]
  -> [PHP-FPM 8.3 / Laravel]
       -> [MySQL 8.x Primary + Read Replica]
       -> [Redis Cluster (cache + queues + sessions)]
       -> [Object Storage (private + public buckets)]
       -> [CDN (CloudFront / BunnyCDN / similar)]

[Queue Workers]
  -> [Horizon / Queue Worker Processes]

[Scheduler]
  -> [Laravel Scheduler (cron every minute)]

[Background Workers]
  -> Media Processing
  -> AI Inference
  -> Report Generation
  -> Notification Dispatch
```

### 4.3 Docker Readiness

- Dockerfile: PHP-FPM 8.3 + Nginx
- docker-compose.yml: Laravel + MySQL + Redis + Queue Worker + Scheduler
- Environment: .env per environment
- Secrets: Docker secrets / vault in production

### 4.4 Observability

- Logs: Laravel Log (Stack) -> structured JSON for production
- Error Tracking: Sentry (when configured)
- Performance: Laravel Telescope (dev), Pulse (production)
- Health Checks:
    GET /health/live   — public liveness probe (minimal info)
    GET /health/ready  — public readiness probe (minimal info)
    GET /health/detail — privileged dependency detail (internal only)
- Uptime: External uptime monitor

---

## 5. Architecture Decisions Record (ADR)

| # | Decision | Choice | Rationale |
|---|----------|--------|-----------|
| ADR-001 | Monolith vs Microservices | Modular Monolith | Team size, complexity, extract later |
| ADR-002 | API Style | Inertia + REST | Inertia for ERP, REST for mobile/external |
| ADR-003 | Auth | OTP-first + Laravel Auth | Mobile-centric BD market |
| ADR-004 | Money computation | Integer minor units (paisa) via bcmath | Exact arithmetic, no float error |
| ADR-004b | Money storage | DECIMAL(20,6) | Never float, full decimal precision |
| ADR-005 | Ledger | Double-entry immutable | Financial integrity (I04, I17) |
| ADR-006 | Media | Signed direct upload + quarantine | Security + performance (I07, I08, I16) |
| ADR-007 | Queue | Redis (upgrading from DB) | Performance + Horizon |
| ADR-008 | Frontend | React 19 + TypeScript strict | Existing starter kit |
| ADR-009 | CSS | Tailwind 4.x | Existing in repo |
| ADR-010 | Module system | Service Provider + ModuleContract | Clean boundaries (I27) |
| ADR-011 | Search | MySQL FTS initially, Meilisearch later | Incremental |
| ADR-012 | i18n | Laravel i18n + React i18n | bn/en first-class |
| ADR-013 | File storage | S3-compatible object storage | Vendor independence |
| ADR-014 | Tenant isolation | Defense-in-depth: context + scope + policy + service + test | MySQL has no native RLS |
| ADR-015 | User identity | Global users (no tenant_id), many-to-many memberships | I23, I24: user is globally unique, multi-tenant |
| ADR-016 | Payment provider pinning | Intent pinned to provider at creation | I25: no silent provider migration of existing intent |
| ADR-017 | Health endpoints | /health/live + /health/ready (public, minimal) + /health/detail (privileged) | Security: minimal info exposure |
| ADR-018 | Fonts | Self-hosted WOFF2 (Inter + Hind Siliguri) | No runtime Google Fonts dependency |
| ADR-019 | Capability enforcement | Backend: capability middleware + service gate; Frontend: UX only | I26: backend is authoritative |

---

*Document Owner: Principal Architect | v1.1.0 | Next Review: Wave 1 Start*
