# STAGING FOUNDATION REPORT

## 1. Environment & Architecture
- **Frozen Baseline:** a8e60b54e63d1522799187253a5f18e995674a42
- **Existing Deployment Infrastructure:** NOT PRESENT
- **Selected Architecture:** Laravel Forge / Ubuntu 24.04 VPS (SELECTED ARCHITECTURE, NOT YET DEPLOYED OR PROVISIONED)
- **Selection Rationale:** A unified VPS directly supports both the stateful PHP backend and the Node.js build process without artificially decoupling the Inertia monolith or breaking CSRF/CORS boundaries.

## 2. Infrastructure Requirements
- **Laravel Runtime:** PHP 8.2+ with BCMath, GD, Fileinfo, Intl, PDO_MySQL, Redis.
- **Node/Vite Build:** Node 20+ required for asset compilation (`npm run build`).
- **Database:** MariaDB 10.4.32 natively running alongside the application.
- **Staging Database Isolation:** `raisa_erp_staging` identity explicitly enforced.
- **Database Safety:** `DatabaseSafetyPolicy` already protects `raisa_erp_staging` from destructive commands.
- **Redis:** Required for queues and staging Cache/Session stores.
- **Queue/Scheduler:** FOUNDATION READY / NOT CURRENTLY ACTIVE.
- **Storage:** Local Persistent Storage required for private media.

## 3. Observability & Security
- **Secret Management:** Secrets injected via secure environment interface. `.env` tracking explicitly denied.
- **CI Gate:** Staging Deployment triggers ONLY after exact-SHA GitHub CI matrix success.
- **Release SHA Foundation:** IMPLEMENTED
- **Remote SHA Traceability:** NOT YET DEPLOYED
- **HTTPS & Sessions:** Native HTTPS and SameSite=Lax cookies for Sanctum authentication compatibility.
- **Staging Access Protection:** HTTP Basic Auth or strict VPN IP allowlisting recommended at the infrastructure layer (e.g., Nginx config) to shield incomplete features.

## 4. UI / Smoke Test Capabilities
- **Browser UI Availability:** Landing page, Login page, Register Page, Authenticated Dashboard (ROUTE / HTML RUNTIME AVAILABLE). MANUAL BROWSER VERIFICATION REQUIRED for visual verification.
- **Backend-Only Features:** Registration APIs, Media Upload APIs (BACKEND READY / UI NOT YET BUILT).
- **Registration Smoke Test:** Complete Mobile-first flow testable with synthetic data. Legacy register disabled (returns 410).
- **Media Smoke Test:** Complete image upload pipeline testable with synthetic fixtures.

## 5. Final State
- **Files Added:** `HealthCheckController.php`, `ADR_STAGING_DEPLOYMENT_ARCHITECTURE.md`, `STAGING_ENVIRONMENT_CONTRACT.md`, `STAGING_DEPLOYMENT.md`, `STAGING_FOUNDATION_REPORT.md`
- **Files Modified:** `routes/web.php`
- **Git Hygiene:** Clean diff, no `var_dump`/`dd`. Tests passing.
