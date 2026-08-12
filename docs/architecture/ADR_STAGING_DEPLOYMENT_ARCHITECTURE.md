# ADR: Staging Deployment Architecture

## Status
Proposed / Pre-Commit

## Context
Raisa ERP Enterprise OS requires a permanent, browser-visible staging (Live Preview) environment to conduct visual and functional PA reviews before enterprise locks. The current setup relies exclusively on exact-SHA local tests and GitHub Actions CI matrices, which proves algorithmic correctness but does not allow non-local visual inspection.

The application stack requires:
- PHP >= 8.2 (with GD, bcmath, fileinfo, intl, pdo_mysql, redis)
- Laravel 12.x backend runtime
- Node.js 20.x for Vite asset compilation
- MySQL 8.0 relational database
- Redis for queues/sessions/cache
- Persistent Storage for private media uploads

## Decision
We select **Laravel Forge** (or an equivalent Ubuntu 24.04 VPS with automated Docker/Nginx orchestration) as the designated Staging Deployment Architecture.

This architecture avoids splitting the stateful Inertia/Laravel application across disparate frontend (e.g., Vercel) and backend providers, which introduces unnecessary network hops, cookie/CORS complexity, and weakens our existing security boundaries.

## Rationale
1. **Full-Stack Compatibility:** A unified VPS directly supports both the PHP backend and Node.js build step without requiring complex multi-provider CI/CD pipelines.
2. **Database Proximity:** Keeps the database, Redis, and application within the same network/host, reducing latency.
3. **Observability:** Centralized logs for HTTP requests, queue workers, and cron schedules.
4. **Queue & Scheduler Support:** Native daemon management for Laravel Horizon/Queue and Cron.

## Consequences
- Requires provisioning an Ubuntu 24.04 droplet/instance.
- Secrets must be securely managed in the deployment provider (e.g., Forge Environment editor).
- The CI pipeline must trigger the deployment webhook ONLY upon passing exact-SHA quality gates.
