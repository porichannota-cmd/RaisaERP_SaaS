# RAISA ERP — CERTIFICATION GATES
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## Principle

A wave is CERTIFIED only when ALL gates below pass.
"It compiles" is NOT a gate. (Invariant I22)

---

## Gate 1: Architecture Review

BEFORE any wave begins:
- [ ] Architecture impact reviewed against Master Constitution
- [ ] No invariant violations identified
- [ ] Domain boundaries not violated
- [ ] No duplicate engines planned
- [ ] Database schema reviewed by DB Architect

---

## Gate 2: Implementation Complete

- [ ] All wave scope items implemented
- [ ] No TODO/FIXME/HACK left in production code paths
- [ ] No fake/stub implementations in production code
- [ ] All controllers thin (logic in services/actions)
- [ ] No business logic in Blade/TSX templates

---

## Gate 3: Unit Tests Pass

- [ ] All business logic unit tested
- [ ] Financial calculations covered
- [ ] Money arithmetic tested (no float)
- [ ] Coverage >= 80% for new domain services

---

## Gate 4: Feature/API Tests Pass

- [ ] All new endpoints have feature tests
- [ ] Happy path tested
- [ ] Error paths tested
- [ ] Validation tested

---

## Gate 5: Tenant Isolation Tests Pass

- [ ] Cross-tenant data access blocked (tested)
- [ ] tenant_id cannot be overridden via request (tested)
- [ ] Multi-tenant factories available for new resources

---

## Gate 6: Authorization Tests Pass

- [ ] Each permission tested for correct allow/deny behavior
- [ ] Unauthorized access returns 403 (not 500 or 404)
- [ ] Role hierarchy tests pass

---

## Gate 7: Financial Invariant Tests (Finance-affecting waves)

- [ ] Ledger balance: debits == credits for all journals
- [ ] Idempotency: duplicate mutations do not double-post
- [ ] No float money in any calculation
- [ ] VAT in liability account (not revenue)
- [ ] Reversal entries correct previous entries

---

## Gate 8: Security Review

- [ ] OWASP Top 10 review checklist completed
- [ ] No secrets in logs (log review)
- [ ] No secrets in frontend bundle (bundle analysis)
- [ ] Rate limiting in place for new auth endpoints
- [ ] Input validation on all new endpoints
- [ ] File upload validation (if applicable)
- [ ] New webhooks have signature verification

---

## Gate 9: Performance Baseline

- [ ] No N+1 queries in new list endpoints
- [ ] All new list endpoints paginated
- [ ] Response time < 200ms for standard reads (verified)
- [ ] No synchronous heavy operations (media, exports, notifications)
- [ ] New heavy operations dispatched to queue

---

## Gate 10: Regression Suite

- [ ] Full existing test suite passes (no regressions)
- [ ] No certified engine bypassed by new wave code
- [ ] Existing API contracts unchanged (or versioned if breaking)

---

## Gate 11: Code Quality

- [ ] PHP: composer pint passes (no style violations)
- [ ] TypeScript: tsc --noEmit passes (no type errors)
- [ ] ESLint: no errors
- [ ] Prettier: formatted
- [ ] No console.log in production code
- [ ] No dd()/dump() in production code

---

## Gate 12: Documentation

- [ ] Wave implementation documented
- [ ] API endpoints documented (OpenAPI spec updated)
- [ ] Any new architecture decisions recorded in ADR
- [ ] walkthrough.md updated

---

## Gate Sign-off Required From

| Gate | Required Reviewer |
|------|------------------|
| Architecture | Principal Architect |
| Security | Security Engineer |
| Financial | FinTech Architect |
| Performance | DevOps/QA Architect |
| Final | Principal Architect |

---

## Wave Certification Record

```
Wave: [Number and Name]
Date Completed: [Date]
All Gates: PASS / FAIL
Architecture Review: [Name, Date]
Security Review: [Name, Date]
Final Sign-off: [Name, Date]
Notes: [Any exceptions or deferred items]
```

---

*Document Owner: QA Architect*

## Additional Certification Gates (Phase 00B)

### Wave 1 / 1A / 1B / 1C
- [ ] Money VO: BIGINT minor units, bcmath arithmetic, no float
- [ ] All mount_minor DB columns are BIGINT SIGNED
- [ ] Cache keys include tenant_id for all tenant-derived data
- [ ] TenantContextManager: all 8 resolver adapters registered

### Wave 2+
- [ ] Scoped authorization grants implemented (AUTHORIZATION_GRANTS.md)
- [ ] Privilege composition negative tests pass (I29)
- [ ] All queue jobs implement TenantScopedJob + carry payload
- [ ] Job context cleared in finally{} (isolation test passes)
- [ ] Domain events implement TenantDomainEvent contract
- [ ] Outbox records contain tenant_id + correlation_id
- [ ] Position assignment: effective-dated (promotion creates new record)

