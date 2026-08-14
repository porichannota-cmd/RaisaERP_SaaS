# ADR: Tenant Admin Permission Bootstrap

## Status
Accepted

## Context
In Wave 2G.0, we established the canonical identity (`TENANT_ADMIN`) for the `Tenant Admin` role using the `TenantIamBootstrapper`. However, the role was created with zero explicit permissions because the IAM architecture strictly relies on `AuthorizationGrant` entries mapped to string-based `Permission` keys. To make the newly bootstrapped `Tenant Admin` functional upon tenant creation without creating a sprawling or unverified permission matrix, a minimum canonical permission set is required.

## Decision
1. **Minimum Canonical Set**: We identified the bare minimum canonical permissions necessary for the Tenant Admin to enter and manage their own tenant workspace identity:
   - `tenant.workspace.access`
   - `tenant.settings.view`
   - `tenant.memberships.view`
   - `tenant.memberships.manage`
2. **Global Permission Registration**: `TenantIamBootstrapper` will safely `firstOrCreate` these canonical permissions.
3. **Tenant-Scoped Authorization Grants**: `TenantIamBootstrapper` automatically assigns these permissions to the `TENANT_ADMIN` role using `AuthScope::TENANT` without a `scope_id` (since tenant context is natively enforced via `ActiveTenantContext`).
4. **Idempotent Application**: All bootstrapping occurs strictly via `firstOrCreate` to guarantee idempotency and avoid duplicates on subsequent tenant creation events.

## Consequences
- **Positive**: `Tenant Admin` role now grants immediate, strictly scoped, intended operational authority over the newly provisioned tenant.
- **Positive**: Fails-closed for unauthorized scopes because no wildcards (`*`) or hidden broad grants are used.
- **Negative**: Adds minor additional `firstOrCreate` queries during tenant provisioning.
