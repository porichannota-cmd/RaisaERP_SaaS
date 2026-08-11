# Architecture Decision Record: Database Environment Safety

## Context
During Wave 1B certification, an errant `php artisan migrate:fresh --database=mysql --env=testing` resolved to the primary local development database (`raisa_erp`) due to the absence of an isolated `.env.testing` and a lack of framework-level command interception. The operation was not aborted, causing a complete wipe of the development database.
To ensure this never recurs, a centralized fail-closed Database Safety Guard is required.

## Decision
We establish a **Database Safety System** operating at both the Artisan command level and the test suite bootstrap level:
1. **Protected Databases**: The system maintains a strict registry of protected database names (`raisa_erp`, production names) which are unconditionally rejected as targets for destructive operations (`migrate:fresh`, `db:wipe`, `migrate:reset`, `migrate:refresh`).
2. **Approved Test Databases**: A registry of allowable testing databases is configured (e.g., `raisa_erp_wave1b_test`, `raisa_erp_test`). Any target must exist in the allowlist or match a strict naming pattern (ending in `_test` or `_testing`).
3. **Environment Isolation**: Destructive commands and database schema resets are ONLY authorized if `APP_ENV=testing` AND the active database is positively identified as an Approved Test Database, AND the host is verified as a safe local isolation target.
4. **Command Interception**: `Illuminate\Console\Events\CommandStarting` is intercepted by `DatabaseSafetyServiceProvider` to abort early before the underlying destructive logic runs.
5. **Test Bootstrap Guard**: `TestCase::setUp()` utilizes the exact same policy (`DatabaseSafetyPolicy`) to abort if the environment configuration falls back to the normal development database silently.
6. **MariaDB vs MySQL 8 Distinction**: Local development natively utilizes MariaDB. Testing certification performed on this environment is recorded accurately as `Local MariaDB Safety Certification`. Authoritative MySQL 8 verification is deferred to isolated CI service containers.
7. **No Casual Overrides**: The system specifically ignores `--force` flags for destructive operations against protected registries. Explicit registry exclusions via `.env.testing` modification are required for bypass.
8. **AI-Agent Operational Rule**: AI agents MUST resolve and verify the isolated target environment prior to issuing destructive artisan commands.

## Consequences
- Testing operations are strictly gated, preventing accidental cross-contamination.
- Missing configuration files (`.env.testing`) fail-closed to the default development DB, which the system immediately identifies as protected and aborts.
- Test initialization overhead is minimally impacted as checks occur precisely once at bootstrap.
