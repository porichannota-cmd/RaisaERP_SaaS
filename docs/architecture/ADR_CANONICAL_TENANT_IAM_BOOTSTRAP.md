# ADR: Canonical Tenant IAM Bootstrap Foundation

## Status
Accepted

## Context
In preparation for Wave 2G (Business Profile & Tenant Provisioning), a blocker was identified: the IAM architecture did not have a defined mechanism to automatically and deterministically bootstrap a canonical "Tenant Admin" role when a new `Tenant` is created. Since the `TenantProvisioningService` must atomically provision a tenant and assign the owner an authoritative administrative role, a mechanism is required to resolve this role without trusting client inputs or hiding global IAM policy within the business provisioning logic.

The repository evidence confirmed that `RoleType::TENANT_SYSTEM` exists, but these roles are strictly instantiated *per tenant*. There is no global table of templates.

## Decision
1. **Canonical Identifier**: We establish `TENANT_ADMIN` as the stable canonical `code` for the Tenant Admin system role.
2. **Dedicated Bootstrapper**: We introduce `TenantIamBootstrapper` to idempotently create and resolve this canonical role for any given tenant.
3. **Immutability of System Roles**: The bootstrapper sets `is_system = true`, ensuring this canonical role is protected from standard user-level deletion.
4. **Separation of Concerns**: The `TenantProvisioningService` (to be built in Wave 2G) will rely entirely on `TenantIamBootstrapper` to obtain the correct `role_id` to assign to the business owner.

## Consequences
- **Positive**: Tenant creation now has a deterministic IAM bootstrap process. Client-side role ID manipulation is completely blocked.
- **Positive**: Existing user-defined roles are unaffected.
- **Negative**: Adds slight overhead to the provisioning transaction (one additional `firstOrCreate` call).
