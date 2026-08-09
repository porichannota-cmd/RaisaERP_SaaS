# RAISA ERP — DOMAIN EVENTS & OUTBOX
**Version:** 1.0.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Principle

Every tenant-scoped domain event MUST carry tenant context in its payload.
Event listeners MUST NOT infer tenant from ambient application state alone.
Outbox records MUST include tenant_id and correlation_id.

---

## 2. Domain Event Contract

```php
interface TenantDomainEvent
{
    public function tenantId(): string;
    public function actorId(): ?string;
    public function actorType(): string;      // USER, SYSTEM, QUEUE_WORKER, etc.
    public function correlationId(): string;
    public function requestId(): ?string;
    public function occurredAt(): DateTimeImmutable;
    public function eventId(): string;        // ULID — globally unique event ID
}

abstract class BaseTenantDomainEvent implements TenantDomainEvent
{
    public readonly string $eventId;
    public readonly DateTimeImmutable $occurredAt;

    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $actorId,
        private readonly string $actorType,
        private readonly string $correlationId,
        private readonly ?string $requestId = null,
    ) {
        $this->eventId    = (string) Str::ulid();
        $this->occurredAt = new DateTimeImmutable();
    }

    public function tenantId(): string       { return $this->tenantId; }
    public function actorId(): ?string       { return $this->actorId; }
    public function actorType(): string      { return $this->actorType; }
    public function correlationId(): string  { return $this->correlationId; }
    public function requestId(): ?string     { return $this->requestId; }
    public function occurredAt(): DateTimeImmutable { return $this->occurredAt; }
    public function eventId(): string        { return $this->eventId; }
}
```

### Example Domain Event

```php
class InvoicePaymentReceivedEvent extends BaseTenantDomainEvent
{
    public function __construct(
        string  $tenantId,
        string  $actorId,
        string  $actorType,
        string  $correlationId,
        public readonly string $invoiceId,
        public readonly int    $amountMinor,    // integer minor units
        public readonly string $currencyCode,
        public readonly string $paymentRef,
    ) {
        parent::__construct($tenantId, $actorId, $actorType, $correlationId);
    }
}
```

---

## 3. Outbox Pattern

For reliable event delivery (especially cross-service or webhook events),
use the transactional outbox pattern:

```sql
domain_event_outbox
  id              CHAR(26) PK ULID
  tenant_id       CHAR(26) NOT NULL INDEX
  correlation_id  CHAR(36) NOT NULL
  event_id        CHAR(26) UNIQUE NOT NULL       -- globally unique, from event
  event_type      VARCHAR(100) NOT NULL          -- fully qualified event class name
  payload         JSON NOT NULL                  -- serialized event data
  status          ENUM('pending','processing','delivered','failed') DEFAULT 'pending'
  attempts        TINYINT UNSIGNED DEFAULT 0
  last_error      TEXT NULL
  deliver_after   TIMESTAMP NULL
  delivered_at    TIMESTAMP NULL
  created_at      TIMESTAMP NOT NULL
  -- NO updated_at (use status transition timestamp tracking)
  INDEX idx_outbox_tenant_status (tenant_id, status)
  INDEX idx_outbox_deliver (status, deliver_after)
```

### Outbox Write Pattern

```php
// Inside the same DB transaction as the domain mutation:
DB::transaction(function () use ($invoice, $payment) {
    // 1. Domain mutation
    $invoice->markPaid($payment);

    // 2. Post ledger entries (same transaction)
    $this->ledger->post([...]);

    // 3. Write event to outbox (same transaction — guaranteed delivery)
    DomainEventOutbox::create([
        'tenant_id'      => app('tenant.id'),
        'correlation_id' => $this->correlationId,
        'event_id'       => $event->eventId(),
        'event_type'     => InvoicePaymentReceivedEvent::class,
        'payload'        => json_encode($event->toArray()),
        'status'         => 'pending',
    ]);
    // If this transaction commits → event guaranteed to be delivered
    // If this transaction rolls back → no event dispatched (consistent)
});
```

### Outbox Dispatcher (background job)

```php
// Runs every 30 seconds via scheduler
class DispatchOutboxEventsJob implements ShouldQueue
{
    public function handle(): void
    {
        $events = DomainEventOutbox::pending()
            ->where('deliver_after', '<=', now())
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        foreach ($events as $outboxRecord) {
            try {
                $event = EventDeserializer::deserialize($outboxRecord);
                event($event); // Dispatch to listeners
                $outboxRecord->update(['status' => 'delivered', 'delivered_at' => now()]);
            } catch (\Throwable $e) {
                $outboxRecord->increment('attempts');
                $outboxRecord->update([
                    'status' => $outboxRecord->attempts >= 5 ? 'failed' : 'pending',
                    'last_error' => $e->getMessage(),
                    'deliver_after' => now()->addMinutes(2 ** $outboxRecord->attempts),
                ]);
            }
        }
    }
}
```

---

## 4. Event Listener Safety Rules

```php
// Listeners MUST extract tenant context from the event, not from app state
class SendInvoicePaidNotificationListener
{
    public function handle(InvoicePaymentReceivedEvent $event): void
    {
        // CORRECT: use tenant from event payload
        $tenant = Tenant::find($event->tenantId());  // explicit from event

        // FORBIDDEN: infer from ambient state
        // $tenantId = app('tenant.id'); // could be wrong or unset in queue context

        $this->notificationService->send(
            tenantId:      $event->tenantId(),      // from event
            correlationId: $event->correlationId(), // from event
            // ...
        );
    }
}
```

---

## 5. Correlation ID Propagation

```
HTTP Request → generates Correlation-ID (UUID) if not in header
  → stored in request lifecycle
  → propagated to: all domain events, all jobs dispatched, all audit logs, all outbox records
  → returned in X-Correlation-Id response header
  → logged with every log entry

Queue job → carries correlation_id in payload
  → propagated to child jobs and events

Correlation-ID allows tracing a business operation across:
  HTTP request → service layer → queue job → event listener → webhook → audit log
```

---

## 6. Domain Event Naming Convention

```
{Entity}{PastTenseVerb}Event

Examples:
  InvoiceCreatedEvent
  InvoicePaidEvent
  PaymentReceivedEvent
  StockAdjustedEvent
  UserRegisteredEvent
  MembershipActivatedEvent
  MembershipSuspendedEvent
  OrderShippedEvent
  WalletCreditedEvent
```

---

*Document Owner: Principal Architect | v1.0.0 | Invariant: I33*
