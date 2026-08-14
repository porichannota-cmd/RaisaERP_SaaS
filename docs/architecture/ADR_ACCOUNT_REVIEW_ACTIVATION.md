# ADR: Account Review & Activation Authorization Foundation (Wave 2F)

## Context
Wave 2F introduces the Account Review and Approval engine. A critical architectural decision was required to determine the authorization boundary for reviewers capable of executing the `PENDING_APPROVAL` -> `ACTIVE` or `REJECTED` state transitions.

Since the ERP SaaS system provisions Tenants *after* activation, new registrants exist in a pre-tenant state. 

## Decision
**PLATFORM ACCOUNT REVIEW AUTHORIZATION: DATABASE-BACKED MINIMAL FOUNDATION**

We have established a surgical, fail-closed platform authorization component (`PlatformAccountReviewAuthorizer`) backed by an explicit assignment ledger (`platform_reviewer_assignments`).

1. **TENANT IAM: PRESERVED / UNCHANGED**
   Tenant roles/permissions are intentionally bypassed for this feature because the user is pre-tenant, and polluting Tenant IAM with platform-level Super Admin capabilities introduces scope leakage and security risks.
2. **CONFIG-BASED REVIEWER ALLOW-LIST: REJECTED**
   Config lists (`.env` or PHP array) were rejected. Reviewer authority must be dynamically manageable, auditable, and server-side verifiable without requiring deployment restarts.
3. **FULL PLATFORM IAM: DEFERRED**
   Rather than building a second massive IAM engine for platform-level roles, we implemented a minimal `capability` assignment (`ACCOUNT_REVIEW`). This allows future upgrades to a full Platform IAM without rewriting the Account Review engine.
4. **INITIAL SUPER ADMIN BOOTSTRAP: DEFERRED / CONTROLLED PROVISIONING REQUIRED**
   We did not hardcode a magic email bypass for the initial admin. Provisioning the first reviewer requires a controlled database seeder or CLI command to be implemented in a future operational Wave.

## Consequences
- The Account Review feature is strictly isolated from Tenant operations.
- Platform reviewers must be explicitly granted the `ACCOUNT_REVIEW` capability via the `platform_reviewer_assignments` table.
- IDOR vulnerabilities are mitigated since authorization asserts the exact server-side platform assignment, ignoring client-provided payloads or generic `auth` middleware.
