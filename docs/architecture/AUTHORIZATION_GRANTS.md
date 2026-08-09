# RAISA ERP — AUTHORIZATION GRANT MODEL
**Version:** 1.0.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Core Principle (Invariant I29)

A permission grant is ALWAYS paired with a scope.
Permissions and scopes are NOT independently unioned.

An effective authorization grant is an atomic triple:

```
{ permission + scope_type + scope_id }
```

A broad scope from one role NEVER widens a sensitive permission from another role.

---

## 2. Grant Model

```sql
-- Replaces the simple role→permission model with scoped grants
authorization_grants
  id              CHAR(26) PK ULID
  membership_id   CHAR(26) NOT NULL FK -> tenant_memberships.id
  permission_key  VARCHAR(100) NOT NULL     -- e.g., 'accounting.ledger.post'
  scope_type      ENUM('TENANT','COMPANY','BRANCH','WAREHOUSE','DEPARTMENT','TERRITORY','OWN') NOT NULL
  scope_id        CHAR(26) NULL             -- NULL for TENANT scope (tenant-wide)
  constraints     JSON NULL                 -- additional constraints (e.g., amount ceiling)
  granted_by      CHAR(26) NOT NULL FK -> users.id
  granted_at      TIMESTAMP NOT NULL
  expires_at      TIMESTAMP NULL            -- NULL = no expiry
  revoked_at      TIMESTAMP NULL
  revoked_by      CHAR(26) NULL
  created_at, updated_at
  INDEX idx_ag_membership (membership_id)
  INDEX idx_ag_permission (permission_key)
  INDEX idx_ag_scope (scope_type, scope_id)
```

---

## 3. Scope Hierarchy

```
TENANT    — Access to all companies/branches within the tenant
COMPANY   — Access to all branches within a specific company
BRANCH    — Access to a specific branch
WAREHOUSE — Access to a specific warehouse (subset of branch)
DEPARTMENT — Access to a specific department
TERRITORY — Access to a geographic territory (distribution)
OWN       — Access to own records only (created_by = self)
```

Scope hierarchy (broadest to narrowest):
```
TENANT > COMPANY > BRANCH > WAREHOUSE/DEPARTMENT > TERRITORY > OWN
```

A narrower scope NEVER inherits from a broader scope of a different role grant.

---

## 4. Multi-Role Grant Composition

When a user holds multiple roles/grants within a membership:

### 4.1 Permission Lookup

```
Authorization check for action X on resource R:

AUTHORIZED if: ∃ grant where:
  grant.permission_key = X
  AND grant.revoked_at IS NULL
  AND (grant.expires_at IS NULL OR grant.expires_at > NOW())
  AND grant.scope covers resource R:
    TENANT scope: R.tenant_id = active_tenant_id
    COMPANY scope: R.company_id = grant.scope_id
    BRANCH scope: R.branch_id = grant.scope_id
    WAREHOUSE scope: R.warehouse_id = grant.scope_id
    DEPARTMENT scope: R.department_id = grant.scope_id
    TERRITORY scope: R.territory_id = grant.scope_id
    OWN scope: R.created_by = current_user_id (or R.user_id = current_user_id)
  AND constraints are satisfied (see §5)
```

### 4.2 Scope Does NOT Bleed Between Grants

```
Example:
  Grant A: accounting.ledger.post + BRANCH + branch_X
  Grant B: accounting.ledger.view + TENANT

Result:
  User can POST ledger entries for branch_X only
  User can VIEW ledger entries for the whole tenant
  User CANNOT POST ledger entries for branch_Y (even though VIEW is tenant-wide)

// FORBIDDEN reasoning:
"User has TENANT scope from grant B, so BRANCH+branch_X scope of grant A
 is widened to TENANT" → This is WRONG.

Each grant is evaluated independently. No scope bleed between grants.
```

### 4.3 Privilege Ceiling / Deny Strategy

```
No explicit "deny" roles (additive model).
Scope constraints act as natural ceilings:
  - A user with BRANCH scope for payment.approve
    cannot approve payments from another branch.
  - A user with OWN scope for reports.export
    cannot export other users' data.

Maximum privilege ceiling (optional, SA-configurable):
  authorization_grant_ceilings
    tenant_id, permission_key, max_scope_type
    -- e.g., payment.approve ceiling = BRANCH (nobody gets TENANT scope)
    -- SA can set hard ceilings per-permission

If ceiling exists: grant is only effective up to ceil(scope, max_scope_type)
```

---

## 5. Constraints

The `constraints` JSON field allows additional guard conditions:

```json
{
  "amount_ceiling_minor": 1000000,  // max approvable amount in minor units
  "currency": "BDT",
  "allowed_account_types": ["ASSET", "EXPENSE"],
  "weekday_only": true,
  "ip_whitelist": ["10.0.0.0/8"],
  "mfa_required": true
}
```

The Policy evaluates constraints after scope match:
```php
class PaymentApprovePolicy
{
    public function approve(User $user, Payment $payment): bool
    {
        $grant = $this->grantRepository->find(
            userId: $user->id,
            permission: 'payment.approve',
            resourceScope: ScopeOf::branch($payment->branch_id)
        );

        if (!$grant) return false;

        // Evaluate constraints
        if ($grant->constraint('amount_ceiling_minor')) {
            if ($payment->amount_minor > $grant->constraint('amount_ceiling_minor')) {
                return false; // Amount exceeds grant ceiling
            }
        }
        if ($grant->constraint('mfa_required') && !$user->hasRecentMfa()) {
            return false;
        }

        return true;
    }
}
```

---

## 6. Scope Type Reference

### TENANT scope

```
scope_type: TENANT, scope_id: NULL
Covers: all resources in the active tenant
Used for: tenant-wide viewers, report viewers, SA delegates
Example: commerce.products.view + TENANT → can view all products in tenant
```

### COMPANY scope

```
scope_type: COMPANY, scope_id: company_id
Covers: all resources belonging to that company
Used for: company finance managers, company HR
Example: hr.payroll.approve + COMPANY + company_A → payroll approval for company A only
```

### BRANCH scope

```
scope_type: BRANCH, scope_id: branch_id
Covers: all resources belonging to that branch
Used for: branch managers, branch cashiers
Example: pos.sale.create + BRANCH + branch_B → can create sales at branch B only
```

### WAREHOUSE scope

```
scope_type: WAREHOUSE, scope_id: warehouse_id
Covers: inventory resources in that warehouse
Used for: warehouse managers, stock controllers
Example: inventory.stock.adjust + WAREHOUSE + wh_01 → stock adjustments in warehouse 01 only
```

### DEPARTMENT scope

```
scope_type: DEPARTMENT, scope_id: department_id
Covers: resources associated with that department
Used for: department heads, department staff
Example: hr.leave.approve + DEPARTMENT + dept_accounts → leave approval for accounts dept
```

### TERRITORY scope

```
scope_type: TERRITORY, scope_id: territory_id
Covers: distribution/field sales resources in a geographic territory
Used for: territory managers, area sales managers
Example: distribution.order.confirm + TERRITORY + ter_dhaka_north
```

### OWN scope

```
scope_type: OWN, scope_id: NULL
Covers: resources created by or assigned to the user themselves
Used for: basic staff, customer self-service
Example: reports.export + OWN → can export own reports only
Example: profile.update + OWN → can update own profile only
```

---

## 7. Grant Resolution Service

```php
class AuthorizationGrantResolver
{
    /**
     * Find the most specific effective grant for the given permission on the resource.
     * Returns null if no grant covers the resource.
     */
    public function resolve(
        string  $userId,
        string  $tenantId,
        string  $permissionKey,
        Scopeable $resource,  // implements: tenantId(), companyId(), branchId(), etc.
    ): ?AuthorizationGrant {

        $membership = TenantMembership::active($userId, $tenantId)->firstOrFail();

        return AuthorizationGrant::query()
            ->where('membership_id', $membership->id)
            ->where('permission_key', $permissionKey)
            ->active() // not revoked, not expired
            ->get()
            ->first(fn($grant) => $grant->covers($resource));
    }

    /**
     * Check if user has a grant covering the given resource.
     */
    public function can(string $userId, string $tenantId, string $permission, Scopeable $resource): bool
    {
        $grant = $this->resolve($userId, $tenantId, $permission, $resource);
        if (!$grant) return false;
        return $grant->constraintsSatisfied($resource);
    }
}
```

---

## 8. Mandatory Privilege-Composition Tests

The following tests MUST exist for every wave that introduces new permissions:

```php
// Test: Tenant-scope view does NOT upgrade branch-scope approve
test('tenant view grant does not widen branch approve grant', function () {
    $user = createUserWithGrants([
        ['permission' => 'payment.approve', 'scope' => 'BRANCH', 'scope_id' => $branchA->id],
        ['permission' => 'payment.view',    'scope' => 'TENANT'],
    ]);
    $payment = Payment::factory()->for($branchB)->create();

    // Can VIEW (tenant scope)
    expect($resolver->can($user->id, $tenantId, 'payment.view', $payment))->toBeTrue();
    // Cannot APPROVE (only branch_A scope, payment is branch_B)
    expect($resolver->can($user->id, $tenantId, 'payment.approve', $payment))->toBeFalse();
});

// Test: Amount ceiling constraint is enforced
test('amount ceiling constraint blocks high-value approval', function () {
    $grant = AuthorizationGrant::factory()->create([
        'permission_key' => 'payment.approve',
        'scope_type'     => 'BRANCH',
        'constraints'    => ['amount_ceiling_minor' => 100000], // ৳1000.00
    ]);
    $largePayment = Payment::factory()->create(['amount_minor' => 500000]); // ৳5000.00
    expect($policy->approve($user, $largePayment))->toBeFalse();
});

// Test: Expired grant is not honored
test('expired grant is not effective', function () {
    createGrant(['permission' => 'accounting.ledger.post', 'expires_at' => now()->subDay()]);
    expect($resolver->can($user->id, $tenantId, 'accounting.ledger.post', $ledger))->toBeFalse();
});

// Test: Revoked grant is not honored
test('revoked grant is not effective', function () {
    createGrant(['permission' => 'hr.payroll.approve', 'revoked_at' => now()]);
    expect($resolver->can($user->id, $tenantId, 'hr.payroll.approve', $payroll))->toBeFalse();
});
```

---

## 9. Integration with Spatie Permission

Spatie laravel-permission is used as the role/permission registry and assignment mechanism.
The `authorization_grants` table provides the SCOPED layer on top of Spatie.

```
Spatie role: defines which permissions exist and are grouped
authorization_grants: defines WHICH scope a user has for each permission

Check sequence:
  1. Middleware: authenticated + active tenant context
  2. Capability gate (if applicable)
  3. Policy: AuthorizationGrantResolver::can() — scoped check
  4. Constraint evaluation (amount ceilings, MFA requirement, etc.)
```

---

*Document Owner: Security Architect | v1.0.0 | Invariant: I29*
