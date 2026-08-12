# Staging Environment Contract

## Objective
To strictly isolate the staging environment from development, automated testing, and production data, preventing accidental corruption or credential leakage.

## Core Variables
Staging MUST enforce the following environment values via secure secret injection (NOT committed in `.env`):

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.raisa-erp.example.com
APP_RELEASE_SHA=<injected-during-deployment>

# Database explicitly isolated
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=raisa_erp_staging
DB_USERNAME=<secret>
DB_PASSWORD=<secret>

# Infrastructure
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<secret>
REDIS_PORT=6379

FILESYSTEM_DISK=local

# Security Tokens
REGISTRATION_SESSION_HMAC_SECRET=<secret-at-least-32-chars>
SENSITIVE_LOOKUP_SECRET=<secret>

# Third-party (Wave 1C)
OTP_PROVIDER=mock # Or secure staging credentials
MAIL_MAILER=log
```

## Security Rules
1. **Never commit `.env` or `.env.testing`.**
2. **Never expose secrets in the health endpoint.**
3. **Never reuse production database credentials.**
4. **Never run destructive commands against `raisa_erp_staging` (e.g. `migrate:fresh`).** Forward-only migrations via `php artisan migrate --force` are permitted during deploy.
