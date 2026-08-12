# Staging Deployment Operations

## Deployment Trigger
The staging deployment MUST NOT run until all exact-SHA quality gates (Raisa ERP CI, tests, linter) have passed in the `main` branch.

## Safe Deployment Steps
A staging deployment script should execute the following safely:

```bash
# 1. Pull verified exact SHA
git pull origin main

# 2. Install dependencies securely
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci

# 3. Build frontend assets
npm run build

# 4. Safe Forward Migrations (fail if DB is protected incorrectly)
php artisan migrate --force

# 5. Cache optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Ensure Storage link
php artisan storage:link

# 7. Restart queues if active
php artisan queue:restart
```

## Rollback Strategy
- **Application Code:** Revert the Git commit and redeploy the previous exact SHA.
- **Frontend Assets:** Automatically rebuilt during redeployment.
- **Database Migrations:** Rollbacks (`migrate:rollback`) are inherently risky and may destroy data. We strongly prefer **forward-fixing** schema issues. If a rollback is absolutely required, it must be performed manually by the Principal Architect after assessing non-reversible data loss risks.

## Future Auto-Preview Contract
For all future waves (Wave 2C - 2H):
1. Feature Implementation
2. Local Certification & Forensic Lock
3. Controlled Commit & Push
4. Remote exact-SHA CI execution
5. Staging deployment triggers via webhook
6. Principal Architect visually inspects Staging Browser GUI
7. Final Enterprise Lock Granted
