# ADR: User ID Compatibility (Wave 1A)

**Status:** Approved
**Date:** 2026-08-09
**Context:** The Phase 00 architecture documents specified `users.id` as a ULID `CHAR(26)`. However, the certified initial Laravel setup instantiated it as an auto-incrementing `BIGINT`. 

**Decision:**
1. The certified `users` table will **not** be destructively migrated. `users.id` remains `BIGINT`.
2. All foreign keys referencing `users.id` (e.g., `user_id` in `tenant_memberships`) MUST be defined as `unsignedBigInteger` matching the existing type.
3. New first-class Wave 1A domain entities (`tenants`, `tenant_memberships`, `roles`, `positions`) will strictly use `ULID (CHAR(26))` as primary keys in accordance with `DATABASE_GOVERNANCE.md`.
4. This preserves compatibility without violating the "no destructive updates" invariant. Any future transition to global ULIDs for Users requires a separate, dedicated database migration program.
