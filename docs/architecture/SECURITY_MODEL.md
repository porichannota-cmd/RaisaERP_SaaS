# RAISA ERP — SECURITY MODEL
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00A

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | Multi-scope RBAC, capability enforcement backend authority, membership-based auth |

---

## 1. Security Principles

1. **Defense in Depth**: Multiple independent security layers
2. **Least Privilege**: Minimum permissions for function
3. **Default Deny**: Deny all, permit explicitly
4. **Zero Trust**: Verify every request regardless of origin
5. **Audit Everything**: All privileged actions leave immutable evidence (I21)
6. **Fail Secure**: On error, deny access
7. **Backend is Authoritative**: Frontend checks are UX only (I26)

---

## 2. Authentication Architecture

### 2.1 Primary Flow: Mobile OTP

```
User: Country + Mobile
  -> POST /api/v1/auth/otp/send
  -> Server: validate format, rate limit, generate OTP (CSPRNG)
  -> SMS Adapter: dispatch OTP (queue)
  -> OTP stored: bcrypt hash, expires_at, attempt_count

User: enters 6-digit OTP
  -> POST /api/v1/auth/otp/verify
  -> Server: check expiry, check attempts (<5), bcrypt verify
  -> On success: create session, set active_tenant_session
  -> OTP consumed (single-use)
  -> OTP VALUE NEVER LOGGED (I10)
```

### 2.2 OTP Security Parameters
```
Length:           6 digits
Generation:       CSPRNG (random_int)
Storage:          bcrypt hash (rounds=4 — fast enough for 6-digit)
Expiry:           5 minutes
Max attempts:     5 (then lock mobile for 15 minutes)
Resend cooldown:  60 seconds
Rate limit:       3 sends per 10 minutes per mobile
                  10 sends per hour per IP
Consumed:         One-time only, marked consumed on success
```

### 2.3 Session Architecture (Post-Login)

```sql
sessions (Laravel standard + extended)
  id             VARCHAR(255) PK
  user_id        CHAR(26) NULL FK -> users.id
  ip_address     VARCHAR(45) NULL
  user_agent     TEXT NULL
  payload        LONGTEXT (encrypted)
  last_activity  INT
  -- Active tenant stored in active_tenant_sessions, not here
```

### 2.4 MFA (Multi-Factor Authentication)

```
Type:            TOTP (RFC 6238, 30-second window)
App:             Any TOTP authenticator
Recovery codes:  8 codes, bcrypt hashed, single-use
Enrollment:      Optional for users; MANDATORY for SA, TA, Financial roles
Backup:          Recovery codes must be saved before MFA activates
Grace window:    ±1 period tolerance (clock drift)
```

---

## 3. Authorization Model (REVISED v1.1.0)

### 3.1 RBAC Architecture

```
Global User
  -> Tenant Membership (user+tenant)
    -> Membership Roles (role_key + scope constraints)
      -> Permissions (derived from role definitions)
```

Authorization is NEVER based on global user role alone.
It is ALWAYS based on the membership-scoped role in the active tenant context.

### 3.2 Scope Hierarchy

```
Platform > Tenant > Company > Branch > Warehouse > Department > Territory > Own-record
```

Each scope is NARROWER than the one above. Authorization checks the narrowest applicable scope.

### 3.3 Multi-Scope Permission Resolution

```php
class PermissionResolver
{
    public function resolve(User $user, string $tenantId): PermissionSet
    {
        $membership = TenantMembership::forUser($user->id)->forTenant($tenantId)->active()->firstOrFail();

        // Load ALL roles for this membership
        $roles = $membership->roles()->active()->get();

        // Union permissions from all roles
        $permissions = collect();
        foreach ($roles as $membershipRole) {
            $rolePermissions = RolePermissionRegistry::getPermissions($membershipRole->role_key);
            $permissions = $permissions->merge($rolePermissions->withScope([
                'company_id'    => $membershipRole->company_id,
                'branch_id'     => $membershipRole->branch_id,
                'department_id' => $membershipRole->department_id,
            ]));
        }

        return new PermissionSet(permissions: $permissions->unique('key'));
    }
}
```

### 3.4 Multi-Role Union Rules

- Permissions are ADDITIVE (union of all active roles in the current tenant)
- Scope constraints are per-role (a user may have Sales permissions at Branch A and Stock permissions at Branch B)
- A DENIED permission in one role does NOT override a GRANTED permission in another role (no deny lists)
- Scope intersection applies when accessing a resource: the user's permission must cover the resource's scope

### 3.5 Permission Naming Convention

```
{domain}.{resource}.{action}

Examples:
  commerce.products.view
  commerce.products.create
  commerce.products.update
  commerce.products.delete
  accounting.ledger.view
  accounting.ledger.post
  hr.payroll.approve
  platform.tenants.impersonate    -- SA only
  platform.tenants.cross_access   -- SA only, audited
```

### 3.6 Backend is ALWAYS Authoritative (I26)

```php
// EVERY API endpoint enforces server-side authorization
public function update(Request $request, string $id): JsonResponse
{
    $product = Product::findOrFail($id); // TenantScope applied
    $this->authorize('update', $product); // Policy verifies: permission + tenant + scope
    // ... business logic
}

// Policy enforces ALL layers
class ProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        // Layer 1: Permission check
        if (!$this->permissions->can('commerce.products.update')) return false;
        // Layer 2: Tenant scope check
        if (app('tenant.id') !== $product->tenant_id) return false;
        // Layer 3: Branch scope check (if role is branch-scoped)
        if ($this->isBranchScoped() && $product->branch_id !== $this->scope->branch_id) return false;
        return true;
    }
}
```

Frontend `hasCapability()` / `hasPermission()` = UX guidance only.
They show/hide UI elements. They do NOT authorize anything.

---

## 4. Capability vs Permission

| Aspect | Capability | Permission |
|--------|-----------|-----------|
| Purpose | Feature availability for the business type | What a user is allowed to do |
| Source | BusinessType + TenantCapabilityOverride + FeatureFlag | Role assignment via RBAC |
| Backend enforcement | Via module route guards + service checks | Via Laravel Policy/Gate |
| Frontend | `hasCapability('kot_kitchen')` — UX only | `hasPermission('commerce.orders.create')` — UX only |
| Both required | A capability must be enabled AND user must have permission | See left |

A capability gate blocks a feature for the entire tenant.
A permission gate blocks a specific user from a specific action.
Both must pass. Either alone is insufficient.

---

## 5. API Security

### 5.1 CSRF Protection
- Inertia SPA: CSRF token via Inertia header (X-XSRF-TOKEN)
- REST API: Stateless (Laravel Sanctum API tokens)
- All state-mutating endpoints: CSRF verified

### 5.2 Rate Limiting
```php
RateLimiter::for('otp_send', fn($req) => [
    Limit::perMinutes(10, 3)->by($req->input('mobile')),
    Limit::perHour(10)->by($req->ip()),
]);

RateLimiter::for('otp_verify', fn($req) =>
    Limit::perMinutes(15, 5)->by($req->input('mobile'))
);

RateLimiter::for('api', fn($req) =>
    Limit::perMinute(60)->by($req->user()?->id ?: $req->ip())
);

RateLimiter::for('api_write', fn($req) =>
    Limit::perMinute(30)->by($req->user()?->id ?: $req->ip())
);
```

### 5.3 Security Headers
```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), camera=(), microphone=()
Content-Security-Policy: [defined per environment]
Strict-Transport-Security: max-age=31536000; includeSubDomains (production)
```

---

## 6. Secret Management

| Secret Type | Storage | Access Pattern |
|------------|---------|---------------|
| App encryption key | .env / secrets manager | Server only |
| DB credentials | .env / secrets manager | Server only |
| Provider API keys | Encrypted DB column | Service layer only — NEVER returned to browser |
| SMTP password | Encrypted DB column | Service layer only |
| OTP hash | bcrypt in otp_records | Verify only, consumed after use |
| User password | bcrypt in users | Verify only |
| MFA secret | Encrypted in users | Enrollment + verification only |
| Recovery codes | bcrypt in mfa_recovery_codes | Verify once, mark consumed |
| Session tokens | Opaque cookie | Server-side lookup |

---

## 7. Health Endpoints (REVISED v1.1.0)

```
GET /health/live
  -> Public. No authentication.
  -> Response: {"status":"ok"} or {"status":"down"}
  -> Reveals: nothing about dependencies or internals.
  -> Purpose: Kubernetes/load balancer liveness probe.

GET /health/ready
  -> Public. No authentication.
  -> Response: {"status":"ready"} or {"status":"not_ready","reason":"maintenance"}
  -> Reveals: only whether traffic should be sent. No internal details.
  -> Purpose: Readiness probe before accepting traffic.

GET /health/detail
  -> REQUIRES: internal network IP OR privileged API token (X-Health-Token header)
  -> Response: {
       "database": {"status":"ok","latency_ms":12},
       "redis":    {"status":"ok","latency_ms":1},
       "queue":    {"status":"ok","depth":{"default":0,"ledger":0}},
       "storage":  {"status":"ok"},
       "version":  "1.0.0",
       "uptime_s": 86400
     }
  -> NEVER exposed to public internet.
```

---

## 8. Webhook Security

```php
// MANDATORY for all inbound webhooks
class WebhookSignatureVerifier
{
    public function verify(Request $request, string $providerKey): void
    {
        $payload = $request->getContent();
        $timestamp = $request->header('X-Timestamp') ?? $request->header('X-Webhook-Timestamp');
        $signature = $request->header('X-Signature') ?? $request->header('X-Webhook-Signature');

        // Replay protection: reject if timestamp > 5 minutes old
        abort_if(abs(now()->timestamp - (int)$timestamp) > 300, 401, 'Stale webhook');

        // Signature verification
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->getSecret($providerKey));
        abort_unless(hash_equals($expected, $signature), 401, 'Invalid signature');
    }
}
```

---

*Document Owner: Security Architect | v1.1.0 | Invariants: I09, I10, I21, I26*
