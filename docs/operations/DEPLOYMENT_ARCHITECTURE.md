# RAISA ERP — DEPLOYMENT ARCHITECTURE
**Version:** 1.0.0 | **Date:** 2026-08-09

---

## 1. Development Environment (Current)

```
OS: Windows (Developer Machine)
  php artisan serve (port 8000)     <- Laravel + Inertia
  npm run dev (Vite HMR)            <- React dev server
  MySQL 8.x (local)
  Redis (optional for dev, required from Wave 1+)
  Queue: database driver (dev), redis (production)
  Storage: local disk (dev), S3-compatible (production)
```

---

## 2. Production Target Architecture

### 2.1 Single Server (MVP / Launch)

```
[Cloud VPS / Bare Metal]
  ├── Nginx (reverse proxy + SSL termination)
  │     -> PHP-FPM 8.3 (Laravel application)
  ├── MySQL 8.x (same server or managed DB)
  ├── Redis 7.x (same server or managed Redis)
  ├── PHP-FPM workers (Laravel web requests)
  ├── Queue workers (3-5 processes)
  │     php artisan queue:work --queue=ledger,default,notifications,media,exports,ai
  ├── Scheduler (cron, every minute)
  │     * * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1
  └── Supervisor (manages queue workers + restarts)
```

### 2.2 Scaled Architecture (Growth)

```
[Load Balancer (Nginx / Cloud LB)]
  ├── Web Server 1 (PHP-FPM 8.3 + Nginx)
  ├── Web Server 2 (PHP-FPM 8.3 + Nginx)
  └── Web Server N...

[Managed MySQL 8.x Cluster]
  ├── Primary (writes)
  └── Read Replica (reporting/analytics reads)

[Managed Redis Cluster]
  ├── Cache
  ├── Queue
  └── Sessions

[Queue Worker Fleet]
  ├── Worker Pool: ledger (critical, limited concurrency)
  ├── Worker Pool: default (general)
  ├── Worker Pool: media (slow, isolated)
  ├── Worker Pool: notifications (fast)
  └── Worker Pool: exports (slow, low priority)

[Object Storage - S3 Compatible]
  ├── Private bucket (NID, documents, restricted)
  └── Public bucket (product images, CDN origin)

[CDN]
  ├── Product images, banners, public assets
  └── Tenant-scoped paths for isolation
```

---

## 3. Docker Setup

### 3.1 docker-compose.yml (Development)

```yaml
version: '3.8'
services:
  app:
    build: .
    ports: ['8000:8000']
    environment:
      - APP_ENV=local
    volumes:
      - .:/var/www/html
    depends_on: [mysql, redis]

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: raisa_erp
      MYSQL_ROOT_PASSWORD: secret
    ports: ['3306:3306']
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    ports: ['6379:6379']

  queue:
    build: .
    command: php artisan queue:work --queue=ledger,default,notifications,media,exports --tries=3 --sleep=3

  scheduler:
    build: .
    command: /bin/sh -c "while true; do php artisan schedule:run; sleep 60; done"

volumes:
  mysql_data:
```

### 3.2 Dockerfile

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor git unzip curl \
    && docker-php-ext-install pdo pdo_mysql redis opcache bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

EXPOSE 8000
```

---

## 4. Nginx Configuration

```nginx
server {
    listen 80;
    server_name raisaerp.com *.raisaerp.com;
    root /var/www/html/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "upload_max_filesize=50M \n post_max_size=50M";
        fastcgi_read_timeout 300;
    }

    # Block direct upload bypass attempts
    location ~ ^/storage/private/ {
        deny all;
    }

    # Media: only serve approved variants via PHP (not directly)
    location ~ ^/storage/tenants/ {
        deny all;
    }
}
```

---

## 5. CI/CD Pipeline

```yaml
# .github/workflows/ci.yml
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: raisa_erp_test
          MYSQL_ROOT_PASSWORD: secret
      redis:
        image: redis:7
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_mysql, redis, bcmath
      - run: composer install
      - run: php artisan test --parallel
      - run: npm ci && npm run type-check && npm run lint

  deploy:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: # Deploy to production (SSH + rsync or Docker push)
```

---

## 6. Observability

### Logging
- Development: daily files + Laravel Pail tail
- Production: structured JSON -> centralized log aggregation (Loki/ELK)
- Never log: passwords, OTPs, secrets

### Metrics
- Application: Laravel Telescope (dev), Laravel Pulse (production)
- Infrastructure: server CPU, memory, disk, Redis, MySQL
- Business: transaction volume, queue depth, error rates

### Health Endpoints (REVISED v1.1.0)

Three distinct endpoints. Public endpoints reveal MINIMAL information:

```
GET /health/live    — Liveness probe (public)
  Response: {"status":"ok"} or {"status":"down"}
  No dependency details. For load balancer / K8s liveness.

GET /health/ready   — Readiness probe (public)
  Response: {"status":"ready"} or {"status":"not_ready","reason":"maintenance"}
  No internal paths or secrets. For K8s readiness / deployment checks.

GET /health/detail  — Detailed dependency status (PRIVILEGED — internal network or token only)
  Response: {
    "database":  {"status":"ok","latency_ms":12},
    "redis":     {"status":"ok","latency_ms":1},
    "queue":     {"status":"ok","depth":{"default":0,"ledger":0}},
    "storage":   {"status":"ok"},
    "version":   "1.0.0",
    "uptime_s":  86400
  }
  NEVER exposed publicly. Requires internal IP range or X-Health-Token header.
```

### Alerts
- Queue depth > 1000: warning
- Queue workers down: critical
- Error rate spike: warning
- Database latency > 1s: warning
- Disk > 80%: warning
- Failed payments spike: critical

---

*Document Owner: DevOps Architect*
