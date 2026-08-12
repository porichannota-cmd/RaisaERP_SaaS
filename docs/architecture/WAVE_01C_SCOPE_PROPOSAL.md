# RAISA ERP ENTERPRISE OS
# WAVE 1C — SCOPE PROPOSAL
# OTP / Communication Provider Core

**Status:** PROPOSED — PENDING PRINCIPAL ARCHITECT APPROVAL
**Date:** 2026-08-12
**Author:** Principal Enterprise Architect (AI Governance Mode)
**Prerequisite Wave:** Wave 1B d97401a82728b4a04cc29f7aab3bd05b78371e6e (CERTIFIED & LOCKED)

> [!IMPORTANT]
> This is a READ-ONLY architectural proposal. No implementation has been started.
> No Wave 1B certified files have been modified.
> Approval from the Principal Architect is required before any implementation begins.

---

## J. WAVE 1C DISCOVERY FINDINGS

### Repository Structure Discovered

**Existing Domain Infrastructure (from certified Waves 1, 1A, 1B):**
- `app/Domain/Audit/` — `AuditLog` model + `Auditable` trait exist (scaffold only — no migration yet deployed in certified history)
- `app/Domain/Events/Outbox/` — `OutboxEvent` model exists (scaffold only)
- `app/Domain/Events/` — `BaseDomainEvent`, `DomainEvent` contracts exist (scaffold only)
- `database/migrations/2026_08_09_202924_create_outbox_events_table.php` — **exists and is deployed** via certified migration history
- `database/migrations/2026_08_09_203340_create_audit_logs_table.php` — **exists and is deployed** via certified migration history

**Existing Architecture Documents Relevant to Wave 1C:**
- `docs/architecture/DOMAIN_EVENTS.md` — Full outbox and domain event contract defined
- `docs/architecture/AUDIT_MODEL.md` — Full audit schema, AuditService contract defined
- `docs/roadmap/IMPLEMENTATION_WAVES.md` — Wave 1C scope explicitly chartered

**Key Roadmap Directive (IMPLEMENTATION_WAVES.md, Wave 1C):**

> Wave 1C — OTP / Communication Provider Core
> Dependencies: Wave 1A
> Critical Path: YES — W2 (Registration) requires OTP on first step

Wave 1C scope is explicitly defined in the canonical roadmap and is on the critical path to Wave 2 (Registration Engine).

**Why Wave 1C cannot begin Wave 2 scope:**
The roadmap mandates `W1A + W1B + W1C` all certified before Wave 2. Wave 1B is now certified. Wave 1C is the next required prerequisite.

---

## K. WAVE 1C PROPOSED ARCHITECTURE

### Objective

Deliver a **certified, production-grade OTP and Communication Provider Core** that is:
- Rate-limited and brute-force resistant
- SMS-provider-agnostic via adapter pattern
- Safe for CI/test environments (log adapter)
- Compliant with audit and security architecture
- Independently lockable before Wave 2 begins

### Critical Path Context

```
Wave 1A (CERTIFIED) → Wave 1B (CERTIFIED) → Wave 1C (PROPOSED) → Wave 2 (Registration)
```

Wave 2 registration first step requires mobile OTP. Without Wave 1C, Wave 2 cannot begin.

---

## L. WAVE 1C IN-SCOPE / OUT-OF-SCOPE

### IN SCOPE (MUST)

| Capability | Justification |
|-----------|--------------|
| `otp_records` table migration | Core OTP persistence |
| `OtpService` (generate, hash, verify, consume) | Core OTP lifecycle |
| OTP rate limiting: 3 sends/10min/mobile, 10/hr/IP | Security invariant |
| Brute-force protection: 5 attempts → 15-min lockout | Security invariant |
| SMS provider adapter interface (`SmsProviderInterface`) | Adapter pattern |
| Log SMS adapter (always available, dev/CI safe) | CI test-safety |
| `POST /api/v1/auth/otp/send` endpoint | Required by Wave 2 |
| `POST /api/v1/auth/otp/verify` endpoint | Required by Wave 2 |
| OTP security tests (rate limit, brute force, expiry, consumed-once, value-not-logged) | Certification gate |

### IN SCOPE (SHOULD)

| Capability | Justification |
|-----------|--------------|
| MIM SMS adapter (or sandbox adapter) | Production SMS delivery |
| Email infrastructure (Laravel Mailable + queue + log driver) | Wave 2 requires email verification |
| `POST /api/v1/auth/email/send-verification` endpoint | Wave 2 prerequisite |
| Media Gateway upload rate limit (throttle middleware) | Wave 1B deferred item, logical fit here |
| EXIF stripping fixture test | Wave 1B NOT PROVEN item, additive fix |
| Orientation fixture test | Wave 1B NOT PROVEN item, additive fix |
| Explicit pixel-count (width × height) policy | Wave 1B INDIRECT BOUND, additive fix |

### OUT OF SCOPE (DEFER)

| Item | Reason |
|------|--------|
| WhatsApp adapter | Wave 14 (Advanced Communication Gateway) |
| Push notifications | Wave 14 |
| Email template management UI | Wave 14 |
| Real malware scanning engine | Infrastructure Wave (ClamAV/vendor) |
| Quarantine storage | Infrastructure Wave |
| Media domain events/outbox | Wave 2+ (outbox infra exists; events belong with domain mutations) |
| Media variant/thumbnail registry | Wave 4 (product images context) |
| Frontend media uploader (BEFDS) | Wave 1C UI or Wave 4 |
| Wave 2 registration engine | Wave 2 (requires W1C certified first) |
| User table migration | Wave 2 |

---

## M. WAVE 1C SECURITY & TENANT INVARIANTS

### OTP Security Invariants

1. **OTP value MUST NEVER be logged** — store only `otp_hash` (bcrypt or argon2id)
2. **OTP is consumed-once** — `consumed` flag set atomically; second verify fails
3. **Rate limiting is Redis-backed** (or DB-backed fallback) — per mobile AND per IP
4. **Brute force lockout** is per OTP, not per account — 5 wrong attempts → 15-min lockout
5. **OTP expiry** — configurable per purpose (default: 10 minutes)
6. **OTP purpose isolation** — `registration`, `password_reset`, `transaction` are distinct namespaces
7. **OTP is tenant-contextual** when within tenant scope; platform-level for registration

### Tenant Isolation Invariants

1. Tenant-scoped OTP records carry `tenant_id`; registration OTPs use `tenant_id = NULL`
2. No cross-tenant OTP sharing
3. Rate limiting keys are tenant-aware (where applicable)

### Audit Invariants

1. All OTP send events MUST emit `OTP_SENT` audit record (without the OTP value)
2. All OTP verify success/failure events MUST emit `OTP_VERIFIED` / `OTP_FAILED` audit records
3. Audit records use the existing `audit_logs` migration (already deployed)

### IAM Invariants

1. OTP send endpoint is unauthenticated (registration context)
2. OTP verify endpoint is unauthenticated (registration context)
3. Both endpoints require valid CSRF (web middleware stack)
4. Rate limiting enforced at middleware layer — not bypassed by mocking in tests

---

## N. WAVE 1C TEST / CI GATES

### Required Test Coverage

| Test | Classification | Required |
|------|---------------|---------|
| OTP generates, hashes, stores correctly | Unit | MUST |
| OTP verify succeeds on correct value within TTL | Unit | MUST |
| OTP verify fails on expired OTP | Unit | MUST |
| OTP verify fails on wrong value | Unit | MUST |
| OTP is consumed after first successful verify | Unit | MUST |
| Consumed OTP verify fails | Unit | MUST |
| Rate limit: 4th send within 10min fails | Feature | MUST |
| Rate limit: 11th send within 1hr/IP fails | Feature | MUST |
| Brute force: 5 wrong attempts → 15-min lockout | Feature | MUST |
| OTP value is NOT logged in application logs | Security | MUST |
| Log SMS adapter emits OTP in log (not HTTP response) | Unit | MUST |
| SMS adapter interface is swappable | Unit | SHOULD |
| Media upload rate limit: 11th upload fails | Feature | SHOULD (if implemented) |
| EXIF stripping proof with EXIF-bearing fixture | Feature | SHOULD |
| Orientation fixture proof | Feature | SHOULD |
| Explicit pixel-count (width × height) boundary | Unit | SHOULD |

### CI Gates

| Gate | Requirement |
|------|------------|
| All new tests pass | PASS |
| No OTP value in logs (log scan test) | PASS |
| DatabaseSafetyGuardTest | PASS (regression) |
| Full backend regression | 0 failures |
| Frontend build | PASS |
| ESLint/Lint | PASS |
| Remote CI (all 3 workflows) | PASS |
| MySQL 8 CI validation | PASS |

---

## O. REMAINING RISKS

1. **MIM SMS Adapter credentials** — production SMS delivery requires API credentials not present in repository. CI uses Log adapter only. Production delivery is deferred to deployment configuration.
2. **Redis dependency for rate limiting** — CI workflow includes Redis 7.0 service container (confirmed in ci.yml), but local dev must ensure Redis is running.
3. **EXIF stripping NOT PROVEN** — requires synthetic EXIF-bearing fixture creation. Dependent on GD's handling, which has known limitations vs Imagick for preserving then stripping EXIF on re-encode.
4. **Wave 1B deferred items** — if EXIF/orientation/pixel-count tests are folded into Wave 1C, they must not alter any certified Wave 1B production code. Tests are additive only.
5. **Audit log migration already deployed** — `audit_logs` table exists. Wave 1C can emit audit records without additional migrations for the table itself, but will need to verify the schema matches `AUDIT_MODEL.md` contract.

---

## P. GIT STATUS

Working tree is CLEAN after Wave 1B commit.
Two new documentation files created locally (not yet staged or committed):
- `docs/implementation/WAVE_01B_FINAL_CERTIFICATION.md` (this session)
- `docs/architecture/WAVE_01C_SCOPE_PROPOSAL.md` (this session)

These are AWAITING Principal Architect approval before commit.

---

## Q. FINAL VERDICT

**WAVE 1B FINAL SEAL: PASS**

All evidence collected. All matrices verified. Remote CI confirmed across 3 workflows against MySQL 8.0.

**Wave 1C Scope:** Fully chartered in the authoritative roadmap document (`IMPLEMENTATION_WAVES.md`). Architecture is dependency-safe, additive, and does not violate any Wave 1B certified boundaries.

---

## WAVE 1C ACCEPTANCE CRITERIA (DEFINITION OF DONE)

A Wave 1C certification commit is eligible only when:

- [ ] `otp_records` table migration exists and passes against MySQL 8
- [ ] `OtpService` fully implements generate/hash/verify/consume lifecycle
- [ ] Rate limiting is implemented and has passing tests (3/10min + 10/hr/IP)
- [ ] Brute force lockout has passing tests (5 attempts → 15 min lockout)
- [ ] OTP value is not logged (log scan test passes)
- [ ] OTP is consumed-once (consumed flag test passes)
- [ ] Log SMS adapter operational (CI can execute OTP flow without real SMS)
- [ ] `POST /api/v1/auth/otp/send` endpoint passing tests
- [ ] `POST /api/v1/auth/otp/verify` endpoint passing tests
- [ ] Full backend regression: 0 failures
- [ ] Frontend build: PASS
- [ ] All 3 remote CI workflows: PASS
- [ ] MySQL 8 CI: PASS
- [ ] Database Safety regression: PASS (DatabaseSafetyGuardTest)
- [ ] Wave 1B no-regression confirmed: all 9 Wave 1B tests still pass
- [ ] Documentation: `WAVE_01C_REPORT.md` created

**Optional (SHOULD) for Wave 1C certification:**
- [ ] EXIF stripping fixture test
- [ ] Orientation fixture test
- [ ] Explicit pixel-count policy test
- [ ] Media upload rate limit

**NOT required for Wave 1C certification (DEFER):**
- Production malware engine
- Quarantine storage
- WhatsApp/Push adapters
- Wave 2 features

---

*Document Owner: Principal Enterprise Architect*
*Status: PROPOSED — Awaiting PA Approval*
*Version: 1.0.0 | Date: 2026-08-12*
