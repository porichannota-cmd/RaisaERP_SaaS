# RAISA ERP — FINANCIAL INVARIANTS
**Version:** 1.2.0 | **Date:** 2026-08-09 | **Phase:** 00B

## Change Log
| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2026-08-09 | Initial |
| 1.1.0 | 2026-08-09 | Resolved DECIMAL vs integer contradiction, payment provider routing
| 1.2.0 | 2026-08-09 | Freeze BIGINT minor units as canonical standard, ledger schema updated, overflow analysis |

---

## 1. Core Financial Rules

| Invariant | Rule |
|-----------|------|
| I03 | All financial mutations are idempotent. |
| I04 | Ledger history is never silently rewritten. Corrections via reversal entries only. |
| I05 | Money uses integer minor units for computation. DECIMAL(20,6) for storage. NEVER float. |
| I25 | A payment intent is pinned to its originating provider after creation. |

---

## 2. Canonical Money Model (RESOLVED v1.1.0)

The contradiction between DECIMAL(20,6) and integer paisa is resolved:

### Resolution
```
COMPUTATION:   Integer minor units (paisa for BDT). bcmath arithmetic. No float.
STORAGE:       DECIMAL(20,6) — MySQL only. Never FLOAT. Never DOUBLE.
HIGH-PRECISION: DECIMAL(20,10) for FX rates, tax rates, interest rates.
ROUNDING:      HALF_UP applied ONCE at final output per currency scale.
```

These are NOT contradictory — they operate at different layers:
- Integer minor units: used INSIDE the Money value object for arithmetic
- DECIMAL(20,6): used for STORAGE in the database
- The Money VO converts between the two layers deterministically

### Currency Scales (ISO 4217)
```
BDT: 2 decimal places (minor unit: paisa, 1 BDT = 100 paisa)
USD: 2 decimal places (minor unit: cent)
EUR: 2 decimal places (minor unit: cent)
JPY: 0 decimal places (minor unit: yen)
KWD: 3 decimal places (minor unit: fils)
```

### Rounding Rules
```
Standard amounts:       HALF_UP to currency scale
Tax calculation:        HALF_UP per line item. Sum line item taxes. Never sum-then-round.
Invoice total:          HALF_UP to currency scale at finalization
FX conversion:          HALF_UP after full-precision calculation (DECIMAL(20,10) rate)
Interest/fee:           HALF_UP to currency scale after full-precision calculation
Commission:             HALF_UP to currency scale after full-precision rate calculation
```

### Serialization Rules
```
JSON API output:        String ("1234.50") — NEVER numeric (1234.5 or 1234.500000)
Database storage:       DECIMAL(20,6) — e.g., 1234.500000
Internal computation:   Integer (123450 paisa)
Display (UI):           Locale-formatted string with currency symbol
```

### Overflow Analysis
```
DECIMAL(20,6): supports up to 14 integer digits + 6 decimal digits
Maximum value: 99,999,999,999,999.999999
In BDT paisa (integer): maximum ≈ 9,999,999,999,999,999 paisa
                                   ≈ 99,999,999,999,999 BDT
                                   ≈ ~100 trillion taka
This is sufficient for any realistic transaction.
```

### Forbidden
```
FLOAT       — IEEE 754 binary floating point. Precision loss.
DOUBLE      — Same issue.
DECIMAL(10,2) — Insufficient for FX rates and high-precision calculations.
PHP: $amount * $rate  — Native float arithmetic forbidden for money.
JS:  amount * rate     — Native float arithmetic forbidden for money.
JSON: { "amount": 1234.5 } — Numeric float in JSON. Forbidden.
```

---

## 3. Double-Entry Ledger

All financial events produce balanced journal entries.
`SUM(DEBITS) == SUM(CREDITS)` for every journal batch.

### 3.1 Account Types
```
ASSET       Debit increases. Credit decreases.
LIABILITY   Credit increases. Debit decreases.
EQUITY      Credit increases. Debit decreases.
REVENUE     Credit increases. Debit decreases.
EXPENSE     Debit increases. Credit decreases.
```

### 3.2 VAT Allocation Rule
```
Gross Invoice Amount: 1,150.00 BDT (net: 1,000.00, VAT: 150.00 @ 15%)

DEBIT   Accounts Receivable (ASSET)      +1,150.00
CREDIT  Revenue — Sales (REVENUE)                   +1,000.00
CREDIT  VAT Payable (LIABILITY)                       +150.00

VAT collected is a LIABILITY. NEVER treated as revenue. (I04)
```

### 3.3 Wallet Deposit
```
DEBIT   Platform Cash / Bank (ASSET)     +500.00
CREDIT  User Wallet Liability (LIABILITY)           +500.00
```

### 3.4 Wallet Transfer (User A -> User B)
```
DEBIT   User A Wallet Liability          +200.00
CREDIT  User B Wallet Liability                    +200.00
```

---

## 4. Wallet Architecture

### 4.1 Balance Derivation
```
WalletBalance = SUM(CREDIT amounts) - SUM(DEBIT amounts)
                for the wallet's ledger account
```

Balance is DERIVED from ledger. Never mutable counter.

Materialized cache (`wallet_balances.balance`) exists for performance only.
Source of truth: ledger_entries.
On dispute: re-derive from ledger_entries.

### 4.2 Concurrency Safety
- Pessimistic lock on wallet_balances row before mutation
- Check idempotency_key BEFORE lock acquisition
- Post ledger entries inside DB transaction
- Update materialized balance inside same transaction

---

## 5. Payment Provider Routing (REVISED v1.1.0)

### 5.1 Provider Selection (BEFORE Intent Creation)

```
1. Load available payment providers for tenant (priority-ordered)
2. Check health status of each provider
3. Select highest-priority HEALTHY provider
4. Store selected provider in payment intent record
```

### 5.2 Intent Pinning (Invariant I25)

Once a PaymentIntent is created, `provider_key` is LOCKED.

```sql
payment_intents
  id              CHAR(26) PK ULID
  tenant_id       CHAR(26) NOT NULL
  provider_key    VARCHAR(50) NOT NULL  -- PINNED at creation, NOT updatable
  provider_ref    VARCHAR(255) NULL     -- from provider after initiation
  amount          DECIMAL(20,6) NOT NULL
  currency        CHAR(3) NOT NULL
  idempotency_key VARCHAR(128) UNIQUE NOT NULL
  status          ENUM('pending','initiated','succeeded','failed','expired','cancelled','provider_failed')
  failure_reason  TEXT NULL
  initiated_at    TIMESTAMP NULL
  completed_at    TIMESTAMP NULL
  created_at, updated_at
```

### 5.3 Provider Failure Recovery

```
IF provider fails AFTER intent creation:
  1. Mark intent.status = 'provider_failed', record failure_reason
  2. Audit log: PAYMENT_PROVIDER_FAILURE
  3. Notify user: "Payment with [Provider] failed. Please try again."
  4. User initiates NEW payment attempt
  5. System creates NEW payment_intent with NEW idempotency_key
     (provider_key may now be different — new intent, new selection)
  6. Original failed intent remains as historical record

DO NOT: silently mutate intent.provider_key
DO NOT: silently re-attempt the same idempotency_key on a different provider
```

### 5.4 SMS/Email Provider Failover

Non-financial communication providers (SMS, email) MAY use transparent
policy-driven failover. This does NOT apply to payment providers. (I25)

---

## 6. Tax / VAT Ledger

```
On invoice payment:
  AllocationEngine:
    1. net_amount   -> CREDIT Revenue Account
    2. vat_amount   -> CREDIT VAT Payable (LIABILITY)
    3. fee_amount   -> CREDIT Platform Fee Payable (if applicable)
    4. total        -> DEBIT Accounts Receivable or Cash

VAT Payable account holds collected tax until:
  a. Tenant files VAT return (workflow records filing)
  b. Direct government settlement (only via authorized provider API)
  c. Manual reconciliation workflow

NEVER: Mark VAT_PAYABLE as revenue.
NEVER: Assume government payment occurred without confirmed provider transaction.
```

---

## 7. Reconciliation Architecture

```sql
-- Bank statement import
bank_statement_lines
  id, tenant_id, bank_account_id, statement_date, reference,
  description, amount DECIMAL(20,6), direction ENUM('DEBIT','CREDIT'),
  matched BOOLEAN DEFAULT FALSE, matched_to_id CHAR(26) NULL,
  matched_at TIMESTAMP NULL, created_at

-- Provider settlement
provider_settlements
  id, tenant_id, provider_key, settlement_date, settlement_ref,
  total_amount DECIMAL(20,6), fee_deducted DECIMAL(20,6),
  net_settled DECIMAL(20,6), currency CHAR(3),
  status ENUM('pending','partial','complete','disputed'),
  reconciled BOOLEAN DEFAULT FALSE, created_at, updated_at
```

---

## 8. Financial Testing Requirements

Every finance-affecting feature must pass:
- [ ] Unit tests: all calculation functions
- [ ] Idempotency tests: duplicate idempotency_key returns same result
- [ ] Concurrency tests: parallel mutations produce consistent balance (no race conditions)
- [ ] Balance tests: SUM(DEBITS) == SUM(CREDITS) for all journals
- [ ] VAT tests: VAT amount in liability account, not revenue
- [ ] Rounding tests: HALF_UP, per-line-item tax rounding
- [ ] Integer unit tests: no float arithmetic in Money VO
- [ ] Overflow tests: amounts within DECIMAL(20,6) bounds
- [ ] Provider failure tests: no ledger posting on failed provider
- [ ] Reversal tests: correction entries balance correctly

---

## 11. Canonical Money Database Contract (FROZEN v1.2.0)

`sql
-- ALL transactional monetary amounts: BIGINT SIGNED
amount_minor    BIGINT SIGNED NOT NULL
currency_code   CHAR(3) NOT NULL

-- Rates, percentages, ratios: DECIMAL only
tax_rate        DECIMAL(20,10) NOT NULL
exchange_rate   DECIMAL(20,10) NOT NULL
commission_rate DECIMAL(20,10) NOT NULL

-- FORBIDDEN in new schema (examples of what NOT to use):
-- amount FLOAT          -- precision loss, forbidden
-- amount DOUBLE         -- precision loss, forbidden
-- amount DECIMAL(10,2)  -- insufficient precision
-- amount DECIMAL(20,6)  -- old pattern; BIGINT minor units supersedes
`

JSON wire format:
`json
{ "amount_minor": "123450", "currency": "BDT", "formatted": "৳1,234.50" }
`

- mount_minor: STRING (not int) — prevents JSON float precision loss
- ormatted: presentation only — never stored, never used in calculations

See MONEY_MODEL.md for the full canonical specification.

---

*Document Owner: FinTech Architect | v1.2.0 | Invariants: I03, I04, I05, I17, I25, I28*

