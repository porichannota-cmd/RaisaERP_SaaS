# Operations Runbook: Database Safety

## Normal Development Migration
1. Verify `APP_ENV=local`.
2. Ensure you are targeting the `raisa_erp` primary development database.
3. Run `php artisan db:safety-status` to verify that the environment is fully recognized, and no protected databases are accidentally selected.
4. Run standard Laravel migration commands: `php artisan migrate`.
5. **Warning:** `migrate:fresh`, `db:wipe`, and other destructive commands are explicitly BLOCKED against `raisa_erp`. To reset development data, manual database reset or specific authorization overrides are required according to architectural policy.

## Isolated Certification Migration
1. Ensure `.env.testing` is correctly configured with `DB_DATABASE=raisa_erp_wave1b_test` (or current wave test database) and `APP_ENV=testing`.
2. Clear configuration caches: `php artisan config:clear`.
3. Preflight check: `php artisan db:safety-status --env=testing`.
   * The response MUST declare `Approved Test Database: YES` and `Destructive Commands Allowed: YES`.
4. Run destructive operations: `php artisan migrate:fresh --env=testing`.

## Test DB Creation
1. Start MySQL/MariaDB server.
2. From a secure shell, log in to MySQL: `mysql -u root -p`
3. Execute: `CREATE DATABASE raisa_erp_wave1b_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
4. Verify literal name match against the allowlist in `DatabaseSafetyPolicy.php`.

## Test DB Reset
1. Verify the exact test database via `php artisan db:safety-status --env=testing`.
2. Run `php artisan db:wipe --env=testing` or `php artisan migrate:fresh --env=testing`.
3. **NEVER** run these commands without `--env=testing` locally, as it will default to `raisa_erp` and abort.

## Checking Active Target
Operators and AI Agents must consistently execute:
`php artisan db:safety-status`
`php artisan db:safety-status --env=testing`

## CI Use
In CI (GitHub Actions), test instances run in isolated service containers mapped to `mysql:8`. `ci.yml` must manually export `DB_DATABASE=testing` to ensure that standard destructive checks clear the allowlist validation cleanly.

## Incident Response
1. **Freeze Execution:** Do not run rollback, db:wipe, or any further mutative commands.
2. **Review Command:** Check `.bash_history` or agent logs to determine the executed string.
3. **Check Safety Output:** The safety service natively logs block reasons. If bypassed, determine how the registry was overridden.
4. **Preserve Snapshot:** Do a filesystem-level SQL dump if the database has not been completely destroyed, before recovering.
5. **Consult Forensics:** See `docs/implementation/WAVE_01B_DATABASE_INCIDENT.md` for historical guidelines on identifying potential data loss.

> [!WARNING]
> NEVER run `migrate:fresh` until the target is positively identified as disposable using `php artisan db:safety-status`.
