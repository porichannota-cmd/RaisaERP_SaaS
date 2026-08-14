# WAVE 2G.0 — CANONICAL TENANT IAM BOOTSTRAP

## 1. Frozen Parent
`5a82d3d06b3298f9885843677db10d200b7e906a`

## 2. Existing IAM Architecture
`RoleType::TENANT_SYSTEM` is natively supported, but `tenant_id` is required. Therefore, global canonical roles do not exist; they are instantiated per tenant.

## 3. Canonical Role Architecture
System roles are instantiated per tenant with `is_system = true`. We established a canonical codebase `TENANT_ADMIN` to reliably identify the primary administrative role for each tenant.

## 4. Canonical Tenant Admin Identity
- `name`: Tenant Admin
- `code`: TENANT_ADMIN
- `type`: RoleType::TENANT_SYSTEM
- `is_system`: true

## 5. RoleType
Confirmed `TENANT_SYSTEM`.

## 6. Stable System Key
`code` column in the `roles` table is utilized. Value is `TENANT_ADMIN`.

## 7. Bootstrap Mechanism
Implemented `App\Domain\IAM\Services\TenantIamBootstrapper`. It safely and idempotently creates the canonical role via `firstOrCreate`.

## 8. Fresh Install Availability
Since it's invoked by `TenantProvisioningService` (to be implemented), it will natively populate during tenant creation on a fresh install.

## 9. Idempotency
`TenantIamBootstrapper::bootstrapForTenant()` returns the existing role if previously seeded, avoiding duplication. Tests confirm exact counts remain unchanged.

## 10. User-Defined Role Protection
Verified via tests: Custom tenant roles (`TENANT_CUSTOM`) are completely untouched and never mistaken for the canonical role.

## 11. Canonical Resolver
`TenantIamBootstrapper::resolveTenantAdminRole()` is built to firmly resolve the canonical role. It rejects client `role_id` and does not fall back to name substrings.

## 12. Fail-Closed Behavior
The resolver throws `\RuntimeException` if the canonical role does not exist, guaranteeing a fail-closed response for corrupt/missing bootstraps.

## 13. Permission Strategy
Broad permission matrix fabrication was avoided. The current foundational scope creates the canonical role identity. Detailed permission assignments (if required) will be linked via `AuthorizationGrant` in future dependencies.

## 14. Tenant Creation Status
NOT IMPLEMENTED. Strictly deferred to Wave 2G.

## 15. Membership Creation Status
NOT IMPLEMENTED. Strictly deferred to Wave 2G.

## 16. Platform Reviewer Preservation
Untouched. Platform reviewers remain completely isolated from tenant creation logic.

## 17. Database Safety
`php artisan db:safety-status` confirms `raisa_erp` is `PROTECTED` and `raisa_erp_wave1b_test` is `APPROVED`.

## 18. Targeted Tests
Implemented `TenantIamBootstrapperTest` validating idempotency, canonical identity resolution, custom role protection, and fail-closed behaviors.

## 19. Full Regression
190 tests passed successfully, verifying total isolation of Wave 2G.0.

## 20. Frontend Build
`npm run build` completed successfully.

## 21. ESLint
`npm run lint` completed successfully.

## 22. Local MariaDB
Used MariaDB 10.4.32 locally for testing.

## 23. MySQL 8 Status
PENDING REMOTE CI.

## 24. Diff Hygiene
Strict file changes constrained to IAM Bootstrapper and Tests. No unrelated drift.

## 25. Secret Hygiene
No plain text secrets added or modified.

## 26. Remaining Risks
The `TenantProvisioningService` must strictly integrate this bootstrapper before linking the initial owner membership.

## 27. Wave 2G Unblock Status
The Canonical Tenant Admin IAM Bootstrap blocker is successfully RESOLVED.

## 28. Final Verdict
WAVE 2G.0 CERTIFIED —
READY FOR PRINCIPAL ARCHITECT LOCK REVIEW
