# RAISA ERP — EFFECTIVE-DATED POSITION ASSIGNMENT HISTORY
**Version:** 1.0.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Principle (Invariant I35)

Historical position assignments MUST NOT be mutated.
Every promotion, transfer, or role change creates a NEW position assignment record.
The previous assignment is CLOSED with an `effective_to` date.
Historical documents (invoices, approvals, payroll) resolve the position valid AT EVENT TIME.

---

## 2. Effective-Dated Schema

```sql
position_assignments
  id              CHAR(26) PK ULID
  user_id         CHAR(26) NOT NULL FK -> users.id
  tenant_id       CHAR(26) NOT NULL FK -> tenants.id
  position_code   VARCHAR(50) NOT NULL      -- e.g., 'TA', 'DIR-FIN', 'DD', 'CUST'
  reference_number VARCHAR(30) NOT NULL UNIQUE  -- e.g., 'TA-2026-Q8M7R2P4' (immutable per record)
  status          ENUM('active','ended','cancelled') DEFAULT 'active'
  effective_from  DATE NOT NULL
  effective_to    DATE NULL                 -- NULL = currently active
  ended_by        CHAR(26) NULL FK -> users.id
  ended_reason    VARCHAR(255) NULL
  notes           TEXT NULL
  created_at, updated_at
  INDEX idx_pa_user_tenant (user_id, tenant_id)
  INDEX idx_pa_user_tenant_status (user_id, tenant_id, status)
  INDEX idx_pa_effective (user_id, tenant_id, effective_from, effective_to)
  UNIQUE idx_ref_num (reference_number)
```

---

## 3. State Machine

```
created → active
active  → ended (on promotion, transfer, resignation, termination)
active  → cancelled (if assignment was in error)

An 'ended' record is IMMUTABLE after closing.
A 'cancelled' record explains the error for audit purposes.
```

---

## 4. Promotion / Transfer Flow

```
User A is promoted from Branch Manager (BRMGR) to Finance Director (DIR-FIN):

Before:
  position_assignments row 1:
    user_id = A, position_code = 'BRMGR', reference_number = 'BRMGR-2024-K3P9X1M5'
    status = active, effective_from = 2024-01-15, effective_to = NULL

Promotion on 2026-08-09:
  Step 1: Close old assignment
    UPDATE position_assignments SET
      status = 'ended',
      effective_to = '2026-08-08',  -- one day before new assignment
      ended_by = hr_user_id,
      ended_reason = 'Promoted to Finance Director'
    WHERE id = row_1_id

  Step 2: Create new assignment (new reference number)
    INSERT INTO position_assignments (
      user_id = A,
      position_code = 'DIR-FIN',
      reference_number = 'DIR-FIN-2026-B7N2W4F1',  -- NEW code, generated fresh
      status = 'active',
      effective_from = '2026-08-09',
      effective_to = NULL,
    )

After:
  position_assignments row 1:  status=ended, effective_to='2026-08-08'  (history)
  position_assignments row 2:  status=active, effective_from='2026-08-09'  (current)

user.global_user_id is UNCHANGED (I24)
```

---

## 5. Point-in-Time Position Resolution

```php
class PositionAssignmentService
{
    /**
     * Get the position that was ACTIVE at a specific point in time.
     * Used for: audit queries, historical invoice attribution,
     *           payroll period attribution, approval history.
     */
    public function getPositionAtTime(string $userId, string $tenantId, \DateTimeInterface $at): ?PositionAssignment
    {
        return PositionAssignment::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('effective_from', '<=', $at->format('Y-m-d'))
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')                     // currently active
                  ->orWhere('effective_to', '>=', $at->format('Y-m-d')); // or covers the date
            })
            ->whereIn('status', ['active', 'ended'])              // not cancelled
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Get the CURRENTLY ACTIVE position for a user in a tenant.
     */
    public function getCurrent(string $userId, string $tenantId): ?PositionAssignment
    {
        return PositionAssignment::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('effective_to')
            ->first();
    }

    /**
     * Get full assignment history for a user in a tenant.
     */
    public function getHistory(string $userId, string $tenantId): Collection
    {
        return PositionAssignment::query()
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->orderBy('effective_from', 'desc')
            ->get();
    }
}
```

---

## 6. Historical Audit Linkage

When a document is created that may later reference who created it with what position:

```sql
-- Link audit-significant records to position assignment at time of creation
invoices
  approved_by_user_id      CHAR(26) NULL FK -> users.id
  approved_at              TIMESTAMP NULL
  -- Position resolved at approval time for historical accuracy:
  approved_position_ref    VARCHAR(30) NULL  -- e.g., 'DIR-FIN-2026-B7N2W4F1'
  -- This is a snapshot, not a live FK, because position may change after approval

payroll_runs
  approved_by_user_id      CHAR(26) NULL
  approved_position_ref    VARCHAR(30) NULL  -- snapshot at approval time

purchase_orders
  approved_by_user_id      CHAR(26) NULL
  approved_position_ref    VARCHAR(30) NULL
```

---

## 7. Mandatory History Tests

```php
test('promotion creates new assignment without mutating old', function () {
    $old = PositionAssignment::factory()->active()->create([
        'position_code' => 'BRMGR', 'effective_from' => '2024-01-15',
    ]);
    $oldRef = $old->reference_number;

    app(PositionAssignmentService::class)->promote(
        userId: $old->user_id,
        tenantId: $old->tenant_id,
        newPositionCode: 'DIR-FIN',
        effectiveFrom: '2026-08-09',
    );

    // Old record is ended (not deleted, not updated in content)
    $old->refresh();
    expect($old->status)->toBe('ended');
    expect($old->effective_to)->toBe('2026-08-08');
    expect($old->reference_number)->toBe($oldRef); // unchanged

    // New record exists
    $new = PositionAssignment::current($old->user_id, $old->tenant_id);
    expect($new->position_code)->toBe('DIR-FIN');
    expect($new->reference_number)->not->toBe($oldRef); // new code
});

test('getPositionAtTime returns correct historical position', function () {
    PositionAssignment::factory()->create([
        'position_code' => 'BRMGR', 'effective_from' => '2024-01-15', 'effective_to' => '2026-08-08', 'status' => 'ended',
    ]);
    PositionAssignment::factory()->active()->create([
        'position_code' => 'DIR-FIN', 'effective_from' => '2026-08-09',
    ]);

    $pos = $service->getPositionAtTime($userId, $tenantId, new \DateTime('2025-06-01'));
    expect($pos->position_code)->toBe('BRMGR');

    $pos = $service->getPositionAtTime($userId, $tenantId, new \DateTime('2026-08-09'));
    expect($pos->position_code)->toBe('DIR-FIN');
});
```

---

*Document Owner: HR Domain Architect | v1.0.0 | Invariant: I35*
