# ADR: Business Profile & Tenant Provisioning (Wave 2G)

## Status
Accepted

## Context
Wave 2G requires implementing the Business Profile foundation and the Tenant Provisioning workflow. A Business Profile is the legal and authoritative identity of a business entity prior to its operational activation. A Tenant is the isolated operational workspace representing that business inside the application.

Provisioning a Tenant requires creating the workspace, bootstrapping the IAM (canonical `TENANT_ADMIN` role), creating a `TenantMembership` for the owner, and linking the workspace to the `BusinessProfile` in an atomic, idempotent operation.

## Decisions

### 1. `BusinessProfile` and `BusinessAddress` Schema
We modeled `BusinessProfile` and `BusinessAddress` as distinct entities to handle business registration.
- Uses ULID for primary keys.
- Legal identifiers (Trade License, TIN, BIN) are encrypted using `SensitiveDataCipherInterface` and hashed for lookups using `SensitiveLookupHasherInterface`.
- `owner_user_id` is an explicit reference to the active `User` who creates the profile. It is NOT fillable to prevent tampering.

### 2. Provisioning State Machine
We use an enum `ProvisioningStatus` with exactly three states:
- `DRAFT`: Initial state while data is being collected.
- `READY_FOR_PROVISIONING`: Business profile is complete and the owner's account status is `ACTIVE`.
- `PROVISIONED`: Tenant has been successfully created.
States like `PROVISIONING` or `FAILED` are avoided because provisioning is executed inside a synchronous DB transaction.

### 3. TenantProvisioningService & Atomicity
Tenant creation is strictly handled by `TenantProvisioningService::provision()`.
- Executes inside a database transaction (`DB::transaction`).
- Relies on `BusinessProfile::lockForUpdate()` to prevent race conditions during concurrent requests.
- Provisions the `Tenant`, bootstraps canonical IAM via `TenantIamBootstrapper`, assigns the canonical `TENANT_ADMIN` role via `MembershipRole`, and links the `tenant_id` back to the profile.
- Strict rollback on any failure.

### 4. IAM Reuse
Provisioning explicitly reuses `TenantIamBootstrapper` implemented in Wave 2G.0. No custom role fabrication or permission assignments occur in the provisioning service directly.

### 5. Idempotency
If the provisioning service is called for a `BusinessProfile` that is already `PROVISIONED`, it safely returns the existing `Tenant` rather than attempting duplicate creation.

### 6. Deferred Scope
Business verification, media uploads, subscriptions, and platform-wide permission matrixes are explicitly deferred to future waves (Wave 2H and beyond).

## Consequences
- Business setup is logically decoupled from operational tenant activity, creating a clear audit trail.
- Provisioning is fail-closed and secure against concurrent requests.
- The system prevents unauthenticated users, inactive users, and malicious payloads from creating unauthorized workspaces.
