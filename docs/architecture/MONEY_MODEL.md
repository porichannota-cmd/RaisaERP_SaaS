# RAISA ERP — CANONICAL MONEY MODEL
**Version:** 1.0.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Source of Truth

**ONE canonical representation for transactional monetary amounts:**

```
amount_minor   BIGINT SIGNED     -- integer minor units (e.g., paisa for BDT)
currency_code  CHAR(3)           -- ISO 4217 code
```

DECIMAL is NOT used as the canonical money representation for transactional amounts.
DECIMAL is used ONLY for rates, ratios, and intermediate precision calculations.
FLOAT/DOUBLE are FORBIDDEN everywhere.

---

## 2. Minor Unit Encoding

```
Currency  Exponent  Minor Unit    Example amount
BDT       2         paisa         ৳1,234.50  →  amount_minor = 123450
USD       2         cent          $10.25     →  amount_minor = 1025
EUR       2         cent          €99.99     →  amount_minor = 9999
JPY       0         yen           ¥500       →  amount_minor = 500
KWD       3         fils          KD1.234    →  amount_minor = 1234
OMR       3         baisa         OMR2.500   →  amount_minor = 2500

amount_display = amount_minor / (10 ^ currency_exponent)
amount_minor   = amount_display * (10 ^ currency_exponent)  [exact integer]
```

---

## 3. Currency Metadata Table

```sql
currencies
  code              CHAR(3) PK           -- ISO 4217 alpha: BDT, USD, EUR, JPY
  numeric_code      CHAR(3) UNIQUE       -- ISO 4217 numeric: 050, 840, 978, 392
  name_en           VARCHAR(100)
  name_bn           VARCHAR(100) NULL
  symbol            VARCHAR(10)          -- ৳, $, €, ¥
  minor_unit_exp    TINYINT UNSIGNED     -- decimal places: BDT=2, USD=2, JPY=0, KWD=3
  rounding_method   ENUM('HALF_UP','HALF_EVEN','CEILING','FLOOR') DEFAULT 'HALF_UP'
  rounding_scale    TINYINT UNSIGNED     -- usually equals minor_unit_exp
  is_active         BOOLEAN DEFAULT TRUE
  created_at, updated_at
```

Seeded at platform bootstrap. Never modified by tenants.

---

## 4. Overflow Analysis

```
BIGINT SIGNED range: -9,223,372,036,854,775,808 to 9,223,372,036,854,775,807

BDT (exponent=2):
  Max representable: 92,233,720,368,547,758.07 BDT
  (~92 quadrillion taka — sufficient for any realistic amount)

USD (exponent=2):
  Max representable: ~92 quadrillion dollars — sufficient

JPY (exponent=0):
  Max representable: ~9.2 quintillion yen — sufficient

KWD (exponent=3):
  Max representable: ~9.2 quadrillion KWD — sufficient

OVERFLOW CHECK:
  Before every multiplication:
    if (abs(a) > MAX_SAFE / abs(b)) throw MoneyArithmeticOverflowException
  Use PHP bcmath for intermediate calculations, then convert to int.
```

---

## 5. Money Value Object

```php
final class Money
{
    private const MAX_MINOR = PHP_INT_MAX; // 9,223,372,036,854,775,807

    private function __construct(
        private readonly int    $amountMinor,
        private readonly string $currencyCode,
    ) {}

    // Primary factory — from integer minor units
    public static function ofMinor(int $amountMinor, string $currency): self
    {
        CurrencyRegistry::assertExists($currency);
        return new self($amountMinor, strtoupper($currency));
    }

    // From display string (e.g., "1234.50")
    public static function fromDecimalString(string $decimal, string $currency): self
    {
        $exp = CurrencyRegistry::minorUnitExp($currency);
        // bcmath: exact string arithmetic, no float
        $minor = bcmul($decimal, bcpow('10', (string)$exp, 0), 0);
        return new self((int)$minor, strtoupper($currency));
    }

    // Arithmetic — results stay in minor units
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountMinor + $other->amountMinor, $this->currencyCode);
    }

    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountMinor - $other->amountMinor, $this->currencyCode);
    }

    public function multiplyByRate(string $rate): self
    {
        // Rate is DECIMAL(20,10) string — use bcmath
        $result = bcmul((string)$this->amountMinor, $rate, 10);
        // Round to integer minor units using HALF_UP
        return new self((int)$this->roundHalfUp($result, 0), $this->currencyCode);
    }

    // Percentage application (e.g., 15% VAT)
    public function applyPercentage(string $percentageDecimal): self
    {
        // percentageDecimal = "15.000" means 15%
        $rate = bcdiv($percentageDecimal, '100', 10);
        $vatMinor = bcmul((string)$this->amountMinor, $rate, 10);
        return new self((int)$this->roundHalfUp($vatMinor, 0), $this->currencyCode);
    }

    public function negate(): self { return new self(-$this->amountMinor, $this->currencyCode); }
    public function isZero(): bool { return $this->amountMinor === 0; }
    public function isNegative(): bool { return $this->amountMinor < 0; }
    public function isPositive(): bool { return $this->amountMinor > 0; }
    public function compare(Money $other): int { return $this->amountMinor <=> $other->amountMinor; }

    // Accessors
    public function amountMinor(): int { return $this->amountMinor; }
    public function currency(): string { return $this->currencyCode; }

    // Display conversion (for JSON/UI output only — not used in computation)
    public function toDecimalString(): string
    {
        $exp = CurrencyRegistry::minorUnitExp($this->currencyCode);
        return bcdiv((string)$this->amountMinor, bcpow('10', (string)$exp, 0), $exp);
    }

    private function roundHalfUp(string $value, int $scale): string
    {
        return bcadd($value, '0.' . str_repeat('0', $scale) . '5', $scale);
    }

    private function assertSameCurrency(Money $other): void
    {
        if ($this->currencyCode !== $other->currencyCode) {
            throw new CurrencyMismatchException($this->currencyCode, $other->currencyCode);
        }
    }
}
```

---

## 6. Database Storage

```sql
-- ALL transactional monetary amounts use this pattern
amount_minor    BIGINT SIGNED NOT NULL      -- integer minor units
currency_code   CHAR(3) NOT NULL DEFAULT 'BDT'

-- Examples:
-- invoices: total_amount_minor, paid_amount_minor, balance_due_minor
-- payments: amount_minor
-- ledger_entries: amount_minor
-- wallet_balances: balance_minor
-- salary_components: amount_minor

-- Rates, percentages, ratios — DECIMAL only (never minor units)
tax_rate        DECIMAL(20,10) NOT NULL     -- e.g., 0.1500000000 for 15%
exchange_rate   DECIMAL(20,10) NOT NULL     -- e.g., 110.2500000000 for USD/BDT
commission_rate DECIMAL(20,10) NOT NULL     -- e.g., 0.0250000000 for 2.5%
discount_rate   DECIMAL(20,10) NOT NULL

-- FORBIDDEN in new migrations (do NOT use these patterns):
-- amount FLOAT          -- precision loss
-- amount DOUBLE         -- precision loss
-- amount DECIMAL(10,2)  -- old pattern, DO NOT USE for amounts
-- amount DECIMAL(20,6)  -- old pattern, replaced by BIGINT minor units
```

---

## 7. JSON Money Contract

```json
{
  "amount_minor": "123450",
  "currency": "BDT",
  "formatted": "৳1,234.50"
}
```

Rules:
- `amount_minor`: STRING (not numeric) — prevents JSON float precision loss
- `currency`: ISO 4217 alpha code
- `formatted`: PRESENTATION ONLY — computed at API output layer, never stored
- Incoming API: `amount_minor` as string is parsed to int by FormRequest
- FORBIDDEN: `{ "amount": 1234.5 }` — numeric float in JSON

### PHP API Resource

```php
class MoneyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'amount_minor' => (string) $this->resource->amountMinor(),  // string, not int
            'currency'     => $this->resource->currency(),
            'formatted'    => MoneyFormatter::format($this->resource, $request->locale ?? 'en'),
        ];
    }
}
```

---

## 8. Rounding Rules

```
STANDARD ROUNDING:    HALF_UP (round 0.5 away from zero)
CURRENCY PRECISION:   Per ISO 4217 minor_unit_exp (BDT=2, JPY=0, KWD=3)

Tax calculation:
  1. Calculate tax on each line item: item_minor * tax_rate (bcmath, 10 dp precision)
  2. Round HALF_UP to integer minor units per line item
  3. Sum rounded line item taxes → invoice total tax
  NEVER: sum line items then round (accumulates error differently)

Invoice total:
  1. Sum all line item amount_minor values (integer addition, exact)
  2. Sum all line item tax amounts (already rounded per item)
  3. Grand total = sum(net_minor) + sum(tax_minor)
  No additional rounding needed (all already in integer minor units)

FX conversion:
  1. Get exchange_rate as DECIMAL(20,10)
  2. source_minor * exchange_rate = intermediate (bcmath, 10 dp)
  3. Round HALF_UP to target currency minor units

Commission:
  1. base_amount_minor * commission_rate (DECIMAL(20,10)) via bcmath
  2. Round HALF_UP to minor units
```

---

## 9. Legacy DECIMAL(20,6) Migration Path

Previous Phase 00A documented DECIMAL(20,6) as the storage format.
Phase 00B freezes BIGINT minor units as the canonical standard.

For the implementation:
- Wave 1: Implement Money VO with BIGINT minor units
- Wave 6 (Accounting/Ledger): All new financial tables use BIGINT minor units
- No existing production data to migrate (greenfield)
- DECIMAL(20,6) remains valid ONLY for: tax_rate, exchange_rate, commission_rate, percentage columns

---

## 10. Ledger Entry Schema (Updated)

```sql
ledger_entries
  id                CHAR(26) PK ULID
  tenant_id         CHAR(26) NOT NULL INDEX
  ledger_account_id CHAR(26) NOT NULL
  entity_type       VARCHAR(50) NOT NULL
  entity_id         CHAR(26) NOT NULL
  direction         ENUM('DEBIT','CREDIT') NOT NULL
  amount_minor      BIGINT SIGNED NOT NULL         -- canonical money
  currency_code     CHAR(3) NOT NULL DEFAULT 'BDT'
  idempotency_key   VARCHAR(128) UNIQUE NOT NULL
  note              TEXT NULL
  metadata          JSON NULL
  posted_by_type    VARCHAR(50) NOT NULL           -- actor type
  posted_by_id      CHAR(26) NULL
  correlation_id    CHAR(36) NULL
  created_at        TIMESTAMP NOT NULL
  -- NO updated_at. NO deleted_at. IMMUTABLE.
```

---

*Document Owner: FinTech Architect + Database Architect | v1.0.0 | Invariant: I05, I28*
