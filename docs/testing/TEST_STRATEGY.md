# RAISA ERP — TEST STRATEGY
**Version:** 1.1.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Testing Philosophy

"Implemented does NOT mean Certified." (Invariant I22)

Every wave must include tests before certification.
Tests are not optional. They are architectural requirements.

---

## 2. Testing Stack

| Layer | Tool |
|-------|------|
| PHP Unit / Feature | Pest PHP (already in repo) |
| API Testing | Pest + Laravel HTTP testing |
| Database | SQLite in-memory (per phpunit.xml) |
| Factories | Laravel Model Factories |
| Mocking | Mockery (already in repo) |
| Frontend Unit | Vitest (to be added Wave 1) |
| Frontend E2E | Playwright (to be added Wave 1) |
| Accessibility | axe-playwright |
| Performance | Laravel Telescope + k6 (load testing) |

---

## 3. Testing Layers

### 3.1 Unit Tests
Location: tests/Unit/

- Pure business logic functions (no DB, no HTTP)
- Financial calculation functions
- Money arithmetic
- ID generators
- Capability resolution
- Commission calculation
- Tax calculation
- Idempotency key generation

```php
// Example
test('money arithmetic never uses float', function () {
    $price = Money::fromDecimal('1234.50', 'BDT');
    $vat = $price->multiply('0.15');
    expect($vat->toMinorUnits())->toBe(185175); // in paisa
    expect($vat->toDecimalString())->toBe('185.175');
});
```

### 3.2 Feature Tests
Location: tests/Feature/

- Full HTTP request -> response cycle
- Database interactions
- Authentication flows
- OTP flows
- File upload flows (mocked storage)
- Payment flows (mocked providers)

```php
test('OTP value is never logged', function () {
    Log::spy();
    postJson('/api/v1/auth/otp/send', ['mobile' => '01712345678']);
    Log::shouldNotHaveReceived('info', fn($msg) => str_contains($msg, '123456'));
});
```

### 3.3 Tenant Isolation Tests
Location: tests/Feature/TenantIsolation/

```php
test('tenant cannot access another tenant orders', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $order = Order::factory()->for($tenantA)->create();

    actingAs(User::factory()->for($tenantB)->create())
        ->getJson("/api/v1/orders/{$order->id}")
        ->assertForbidden();
});

test('tenant_id cannot be overridden via request body', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->for($tenantA)->create();

    actingAs($user)
        ->postJson('/api/v1/orders', ['tenant_id' => $tenantB->id, ...])
        ->assertCreated()
        ->assertJsonPath('data.tenant_id', $tenantA->id); // Always tenantA
});
```

### 3.4 Authorization Tests
Location: tests/Feature/Authorization/

```php
test('staff cannot approve payroll without permission', function () {
    $user = User::factory()->withRole('STF')->create();
    actingAs($user)
        ->postJson('/api/v1/payroll/approve', [...])
        ->assertForbidden();
});
```

### 3.5 Financial Invariant Tests
Location: tests/Feature/Financial/

```php
test('ledger is balanced after invoice payment', function () {
    $invoice = Invoice::factory()->create(['total' => '1150.00']);
    processPayment($invoice, '1150.00');

    $entries = LedgerEntry::where('entity_id', $invoice->id)->get();
    $debits = $entries->where('direction', 'DEBIT')->sum('amount');
    $credits = $entries->where('direction', 'CREDIT')->sum('amount');
    expect($debits)->toBe($credits);
});

test('duplicate payment idempotency key does not double charge', function () {
    $key = Str::ulid();
    $result1 = processPayment($invoice, '1150.00', idempotencyKey: $key);
    $result2 = processPayment($invoice, '1150.00', idempotencyKey: $key);
    expect($result2->id)->toBe($result1->id);
    expect(LedgerEntry::count())->toBe(2); // Not 4
});
```

### 3.6 Inventory Invariant Tests
Location: tests/Feature/Inventory/

```php
test('stock cannot go negative without explicit backorder permission', function () {
    $product = Product::factory()->withStock(5)->create();
    expect(fn() => sellProduct($product, qty: 10))
        ->toThrow(InsufficientStockException::class);
});
```

### 3.7 Provider Adapter Tests
Location: tests/Unit/Providers/

```php
test('payment provider fabricates no success on failed response', function () {
    $adapter = new BkashAdapter(config: ['mode' => 'sandbox']);
    $result = $adapter->verify('invalid-ref');
    expect($result->isSuccess())->toBeFalse();
    expect($result->status)->not->toBe('COMPLETED');
});
```

### 3.8 Media Security Tests
Location: tests/Feature/Media/

```php
test('php file upload is rejected', function () {
    postJson('/api/v1/media/preflight', [
        'file_name' => 'shell.php',
        'content_type' => 'image/jpeg',
        'size' => 1024,
    ])->assertUnprocessable();
});

test('storage key cannot be supplied by client', function () {
    // storage_path is always server-generated
    // Verify no client input is used as storage key
});
```

### 3.9 Queue / Job Tests

```php
test('media processing job runs asynchronously', function () {
    Queue::fake();
    uploadFile('product.jpg');
    Queue::assertPushed(ProcessMediaUploadJob::class);
});
```

### 3.10 Security Tests

```php
test('OTP fails after 5 incorrect attempts', function () {
    $mobile = '01712345678';
    sendOtp($mobile);
    repeat(5, fn() => submitWrongOtp($mobile));
    submitCorrectOtp($mobile)->assertTooManyRequests();
});

test('rate limiter blocks repeated login attempts', function () {
    repeat(6, fn() => postJson('/api/v1/auth/login', ['mobile' => '01700000000', 'otp' => 'wrong']));
    postJson('/api/v1/auth/login', [...])
        ->assertStatus(429);
});
```

### 3.11 E2E Tests (Playwright)

Critical user journeys:
- Registration -> OTP -> Profile completion
- Login -> Dashboard
- Create product -> Add to POS -> Invoice -> Payment
- Create purchase -> Receive stock -> Inventory updated

### 3.12 Accessibility Tests

```javascript
test('login page meets WCAG 2.1 AA', async ({ page }) => {
    await page.goto('/login');
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toHaveLength(0);
});
```

### 3.13 Performance Baseline Tests

- API response time < 200ms for standard list endpoints (with pagination)
- API response time < 500ms for complex reports
- No N+1 queries in list endpoints (verified via query count assertions)
- Bundle size < 500KB initial JS (verified via Vite bundle analysis)

---

## 4. Test Naming Convention

```
Unit:    tests/Unit/{Domain}/{Class}Test.php
Feature: tests/Feature/{Domain}/{FeatureName}Test.php
Isolation: tests/Feature/TenantIsolation/{Domain}IsolationTest.php
Authorization: tests/Feature/Authorization/{Domain}AuthTest.php
Financial: tests/Feature/Financial/{Feature}InvariantTest.php
E2E:     tests/E2E/{Journey}Test.spec.ts
```

---

## 5. CI Pipeline Requirements

```yaml
# Every PR must pass:
- php artisan test --parallel         # Feature + Unit
- npm run type-check                  # TypeScript strict
- npm run lint                        # ESLint
- npm run format:check                # Prettier
- composer pint -- --test             # PHP code style
- security audit (npm audit, composer audit)
```

---

*Document Owner: QA Architect | Invariant: I22*

## New Test Categories (Phase 00B)

### Money Model Tests
- [ ] All amount columns are BIGINT, not FLOAT/DOUBLE/DECIMAL
- [ ] Money VO uses bcmath (no native PHP float arithmetic)
- [ ] BIGINT overflow check before large multiplication
- [ ] JSON response: amount_minor is STRING, not numeric
- [ ] Tax rounding: HALF_UP per line item, not sum-then-round

### Scoped Authorization Grant Tests (I29)
- [ ] Tenant-wide VIEW grant does NOT upgrade BRANCH-scoped APPROVE grant
- [ ] Grant covering branch_A does NOT authorize action on branch_B resource
- [ ] Amount ceiling constraint blocks approval above ceiling
- [ ] Expired grant returns 403
- [ ] Revoked grant returns 403
- [ ] Multi-role composition: no scope bleed between grants

### Queue Tenant Isolation Tests (I31, I32)
- [ ] Job uses tenant from payload, not ambient app state
- [ ] Context cleared after successful job
- [ ] Context cleared after failed job (finally{} guaranteed)
- [ ] Tenant A job cannot access Tenant B data
- [ ] Cross-tenant negative test for every new tenant-scoped job

### Cache Safety Tests (I34)
- [ ] Tenant A cache not visible to Tenant B
- [ ] Capability cache invalidated on module disable
- [ ] Permission cache invalidated on membership revocation
- [ ] Cache key includes tenant_id for all tenant-specific data

### Domain Event Tests (I33)
- [ ] Event contains tenant_id and correlation_id
- [ ] Listener reads tenant from event, not app state
- [ ] Outbox record includes tenant_id + correlation_id
- [ ] Failed delivery retried with backoff, not dropped

### Position History Tests (I35)
- [ ] Promotion creates new record, does not mutate old
- [ ] Old record has effective_to set correctly
- [ ] New record has new reference_number
- [ ] getPositionAtTime returns correct historical position
- [ ] global_user_id unchanged after promotion

### Health Endpoint Tests
- [ ] /health/live returns minimal response (no internals)
- [ ] /health/ready returns minimal response
- [ ] /health/detail returns 401/403 without internal token

