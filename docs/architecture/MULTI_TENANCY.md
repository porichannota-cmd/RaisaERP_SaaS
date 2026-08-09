# RAISA ERP — MULTI-TENANCY STRATEGY
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00A

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | Global user identity, membership model, active tenant context, MySQL isolation defense |

---

## 1. Tenancy Model

RAISA ERP uses **Single Database, Shared Schema** multi-tenancy.
Tenant isolation is enforced through defense-in-depth at every application layer.

> **IMPORTANT:** MySQL does not provide Row Level Security (RLS) equivalent to
> PostgreSQL. Application-layer isolation at ALL layers below is MANDATORY and
> complementary. Describing application scopes as equivalent to DB RLS is FORBIDDEN.

### Why not separate databases per tenant?
- Operational overhead at scale is prohibitive for SME-targeted SaaS.
- Shared schema with proper layered scoping achieves the required isolation.
- Can evolve to dedicated databases for Enterprise tiers later.

---

## 2. Global User Identity (REVISED v1.1.0)

A **user is a GLOBALLY UNIQUE human account**. It is NOT a tenant-scoped record.

One human has ONE global identity regardless of how many tenants they belong to.

```sql
users  -- global, no tenant_id
  id              CHAR(26) PK ULID
  global_user_id  VARCHAR(20) UNIQUE NOT NULL  -- USR-2026-XXXXXXXX (immutable)
  mobile          VARCHAR(20) UNIQUE NOT NULL  -- primary identity
  mobile_verified BOOLEAN DEFAULT FALSE
  mobile_verified_at TIMESTAMP NULL
  email           VARCHAR(255) UNIQUE NULL
  email_verified  BOOLEAN DEFAULT FALSE
  email_verified_at  TIMESTAMP NULL
  password_hash   VARCHAR(255) NULL            -- bcrypt(12), nullable
  status          ENUM('pending','active','suspended','banned') DEFAULT 'pending'
  mfa_enabled     BOOLEAN DEFAULT FALSE
  mfa_secret      VARCHAR(255) NULL            -- encrypted
  created_at      TIMESTAMP NOT NULL
  updated_at      TIMESTAMP NOT NULL
  deleted_at      TIMESTAMP NULL               -- soft delete for banned/GDPR only
```

---

## 3. Tenant Hierarchy

```
Platform (RAISA HQ)
  SA (Super Admin)
      Tenants
          Tenant Members (global users with active membership)
          Companies / Legal Entities
              Branches
                  Departments / Warehouses / Facilities
                      Position Assignments / Role Grants
```

| Entity | Description |
|--------|-------------|
| Tenant | Top-level subscriber. Owns subscription. |
| Company | Legal entity within tenant (TIN/BIN holder). |
| Branch | Physical or logical operating unit of a company. |
| Warehouse | Inventory location under a branch. |
| Department | HR/operational grouping under a branch. |

---

## 4. Membership Model (NEW v1.1.0)

Users gain access to tenants through explicit membership records.

```sql
tenant_memberships
  id              CHAR(26) PK ULID
  user_id         CHAR(26) NOT NULL FK -> users.id   -- global user
  tenant_id       CHAR(26) NOT NULL FK -> tenants.id
  status          ENUM('invited','active','suspended','revoked') DEFAULT 'invited'
  invited_by      CHAR(26) NULL FK -> users.id
  joined_at       TIMESTAMP NULL
  suspended_at    TIMESTAMP NULL
  suspension_reason TEXT NULL
  created_at, updated_at
  UNIQUE KEY uq_user_tenant (user_id, tenant_id)
  INDEX idx_tm_tenant (tenant_id)
  INDEX idx_tm_user (user_id)

tenant_membership_roles
  id              CHAR(26) PK ULID
  membership_id   CHAR(26) NOT NULL FK -> tenant_memberships.id
  role_key        VARCHAR(100) NOT NULL     -- e.g., 'tenant_admin', 'sales_manager'
  company_id      CHAR(26) NULL FK -> companies.id   -- NULL = tenant-wide
  branch_id       CHAR(26) NULL FK -> branches.id    -- NULL = company-wide
  department_id   CHAR(26) NULL FK -> departments.id -- NULL = branch-wide
  granted_at      TIMESTAMP NOT NULL
  granted_by      CHAR(26) NOT NULL FK -> users.id
  expires_at      TIMESTAMP NULL
  created_at, updated_at
  INDEX idx_tmr_membership (membership_id)
```

---

## 5. Active Tenant Context Resolution (NEW v1.1.0)

### 5.1 Active Tenant Session Table

```sql
active_tenant_sessions
  id              CHAR(26) PK ULID
  user_id         CHAR(26) NOT NULL FK -> users.id
  session_id      VARCHAR(255) NOT NULL    -- matches sessions.id
  tenant_id       CHAR(26) NOT NULL FK -> tenants.id
  activated_at    TIMESTAMP NOT NULL
  created_at, updated_at
  UNIQUE KEY uq_session (session_id)       -- one active tenant per session
```

### 5.2 Resolution Algorithm

```
HTTP Request arrives
  -> Middleware: ResolveTenantContext
    1. Read session_id from authenticated Laravel session cookie
    2. SELECT active_tenant_sessions WHERE session_id = ?
    3. IF no record:
         a. Load tenant_memberships for user WHERE status = active
         b. IF exactly one: auto-select it (set active_tenant_sessions)
         c. IF multiple: redirect to tenant selector UI
         d. IF zero: return 403 — no active membership
    4. Load tenant from active_tenant_sessions.tenant_id
    5. Verify: tenant.status IN (active, trial) — else suspend message
    6. Verify: membership.status = active — else revocation message
    7. Load membership roles with scope constraints
    8. Derive effective permissions (union of all roles)
    9. Derive capability set (from business type + overrides + flags)
    10. Bind to app container:
         app('tenant')        -> Tenant model
         app('tenant.id')     -> CHAR(26)
         app('membership')    -> TenantMembership model
         app('permissions')   -> PermissionSet value object
         app('capabilities')  -> CapabilitySet value object
```

### 5.3 Tenant Switching

```
POST /api/v1/auth/tenant/switch
  Body: { "tenant_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV" }

Server:
  1. Verify user has active membership in requested tenant
  2. Verify tenant is active
  3. Update active_tenant_sessions record (not insert new — update existing)
  4. Re-derive permissions and capabilities
  5. Return: new context (tenant name, capabilities, menu structure)
  6. Audit log: TENANT_SWITCHED

Client: No tenant_id is trusted from the browser for anything other than
        initiating the switch request, which is then server-verified.
```

### 5.4 Membership Suspension

```
Admin suspends a membership:
  1. Set tenant_memberships.status = suspended
  2. Set suspended_at, suspension_reason
  3. Remove active_tenant_sessions for this user+tenant
  4. Notify user via email/SMS
  5. Audit log: MEMBERSHIP_SUSPENDED

Next request from suspended user:
  -> ResolveTenantContext finds no active session for this tenant
  -> Returns 403 with suspension message and contact info
  -> Other tenant memberships (if any) remain accessible
```

---

## 6. Position / Role Codes (NOT Identity) (NEW v1.1.0)

Role codes such as TA-2026-..., DIR-FIN-2026-..., DD-2026-... are
REFERENCE LABELS within a tenant's operational context, NOT global user identity.

```sql
position_assignments
  id              CHAR(26) PK ULID
  user_id         CHAR(26) NOT NULL FK -> users.id   -- global user
  tenant_id       CHAR(26) NOT NULL FK -> tenants.id
  position_code   VARCHAR(50) NOT NULL    -- e.g., 'TA', 'DIR-FIN', 'DD', 'ENT'
  reference_number VARCHAR(30) NOT NULL   -- e.g., 'TA-2026-Q8M7R2P4' (display only)
  effective_from  DATE NOT NULL
  effective_to    DATE NULL               -- NULL = currently active
  notes           TEXT NULL
  created_at, updated_at
  INDEX idx_pa_user_tenant (user_id, tenant_id)
```

A user's global_user_id (USR-2026-XXXXXXXX) is IMMUTABLE. (Invariant I24)
Position reference numbers may change (promotion, role change). They are never identity.

---

## 7. MySQL Defense-in-Depth Isolation (NEW v1.1.0)

Because MySQL has no native RLS, ALL of the following layers are MANDATORY:

### Layer 1: Server-Resolved Tenant Context (Invariant I18)
Tenant is always resolved from the server-side authenticated session.
Never from request body, query string, or header supplied by the browser.

### Layer 2: Global Query Scope

```php
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            if (app()->bound('tenant.id')) {
                $model->tenant_id = app('tenant.id');
            }
        });
    }
}

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app()->bound('tenant.id') ? app('tenant.id') : null;
        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        }
    }
}
```

### Layer 3: Policy-Level Ownership Verification

```php
// Policy: verify BOTH permission AND tenant ownership
public function update(User $user, Order $order): bool
{
    // 1. Check permission (from RBAC)
    if (!$user->hasPermissionTo('commerce.orders.update')) {
        return false;
    }
    // 2. Verify tenant ownership explicitly (defense layer 3)
    if (app('tenant.id') !== $order->tenant_id) {
        return false; // Cross-tenant access blocked at policy level
    }
    // 3. Check scope constraints (branch/department)
    return $this->withinScope($user, $order);
}
```

### Layer 4: Domain Service Assertion

```php
// All domain services assert tenant context
class OrderService
{
    public function findOrFail(string $orderId): Order
    {
        $order = Order::findOrFail($orderId); // Global scope already applied
        // Explicit assertion — defense layer 4
        abort_if($order->tenant_id !== app('tenant.id'), 403, 'Tenant mismatch');
        return $order;
    }
}
```

### Layer 5: Composite Unique Constraints (where applicable)

```sql
-- Invoice numbers unique per tenant, not globally
UNIQUE KEY uq_invoice_tenant_number (tenant_id, invoice_number)
-- SKU unique per tenant
UNIQUE KEY uq_sku_tenant (tenant_id, sku)
-- Employee code unique per tenant
UNIQUE KEY uq_employee_tenant (tenant_id, employee_code)
```

### Layer 6: Foreign Key Ownership Validation

Policies must verify that referenced entities (branch, company, warehouse)
belong to the same tenant, not just that the FK exists.

### Layer 7: Cross-Tenant Negative Tests (Mandatory)

For every new tenant-scoped resource, a cross-tenant negative test MUST exist:
```php
test('tenant B cannot read tenant A resource', function () {
    $tenantA = createTenantWithUser();
    $tenantB = createTenantWithUser();
    $resource = createResourceFor($tenantA);

    actingAsMemberOf($tenantB)
        ->getJson("/api/v1/resource/{$resource->id}")
        ->assertForbidden();
});
```

---

## 8. Tenant Settings & Custom Domain

```sql
tenants
  id              CHAR(26) PK ULID
  name            VARCHAR(255) NOT NULL
  slug            VARCHAR(100) UNIQUE NOT NULL
  custom_domain   VARCHAR(255) UNIQUE NULL
  domain_verified BOOLEAN DEFAULT FALSE
  domain_verified_at TIMESTAMP NULL
  status          ENUM('trial','active','suspended','churned') DEFAULT 'trial'
  plan_id         CHAR(26) NULL FK -> subscription_plans.id
  settings        JSON NULL           -- encrypted sensitive fields
  trial_ends_at   TIMESTAMP NULL
  created_at, updated_at, deleted_at  -- soft delete only
```

---

## 9. Data Retention on Cancellation

**CRITICAL**: Tenant data is NEVER destroyed on non-payment.

- Non-payment → grace period → status = SUSPENDED
- Protected business functions restricted
- Data preserved fully
- Clear recovery path (payment = instant reactivation)
- Deliberate closure: separate SA-authorized audited workflow

---

## 10. Cross-Tenant Authorization (Super Admin)

```php
// SA cross-tenant access: explicit, audited, not via global scope bypass
class SuperAdminCrossTenantService
{
    public function accessTenantResource(string $targetTenantId, string $reason): void
    {
        // 1. Verify requestor is SA
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        // 2. Log the cross-tenant access intent
        AuditLog::record('SA_CROSS_TENANT_INTENT', [
            'target_tenant' => $targetTenantId,
            'reason' => $reason,
        ]);
        // 3. Set temporary context (scoped, not permanent)
        app()->instance('sa.cross_tenant.id', $targetTenantId);
    }
}
```

---

*Document Owner: Principal Architect | v1.1.0 | Invariants: I01, I02, I18, I23, I24*
