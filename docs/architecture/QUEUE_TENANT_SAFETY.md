# RAISA ERP — QUEUE & JOB TENANT SAFETY
**Version:** 1.0.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Principle (Invariants I31, I32)

Every tenant-scoped queue job MUST carry tenant identity in its payload.
Context MUST be established before domain code executes and cleared afterward.
Tenant context MUST NOT leak between long-running worker jobs.

---

## 2. Tenant-Safe Job Payload Contract

```php
interface TenantScopedJob
{
    public function getTenantId(): string;
    public function getActorId(): ?string;      // User or system actor
    public function getCorrelationId(): string; // Trace cross-service work
    public function getRequestId(): ?string;    // Originating HTTP request
}

trait HasTenantPayload
{
    protected string  $tenantId;
    protected ?string $actorId;
    protected string  $correlationId;
    protected ?string $requestId;

    public function getTenantId(): string    { return $this->tenantId; }
    public function getActorId(): ?string    { return $this->actorId; }
    public function getCorrelationId(): string { return $this->correlationId; }
    public function getRequestId(): ?string  { return $this->requestId; }
}
```

### Job Payload Example

```php
class ProcessMediaUploadJob implements ShouldQueue, TenantScopedJob
{
    use HasTenantPayload;

    public function __construct(
        public readonly string  $mediaUploadId,
        string                  $tenantId,
        ?string                 $actorId      = null,
        string                  $correlationId = '',
        ?string                 $requestId    = null,
    ) {
        $this->tenantId      = $tenantId;
        $this->actorId       = $actorId;
        $this->correlationId = $correlationId ?: (string) Str::uuid();
        $this->requestId     = $requestId;
    }

    public function handle(TenantContextManager $manager): void
    {
        try {
            $manager->resolveForJob([
                'tenant_id'      => $this->tenantId,
                'actor_id'       => $this->actorId,
                'correlation_id' => $this->correlationId,
                'request_id'     => $this->requestId,
            ]);

            // Domain code now runs with correct tenant context
            app(MediaProcessor::class)->process($this->mediaUploadId);

        } finally {
            $manager->clear(); // ALWAYS clear context after job completes
        }
    }
}
```

---

## 3. Dispatching Jobs with Context Propagation

When dispatching from an HTTP request context, propagate tenant payload automatically:

```php
// Always propagate tenant context when dispatching from a request
class JobDispatcher
{
    public function dispatch(ShouldQueue $job): void
    {
        if ($job instanceof TenantScopedJob) {
            // Context already in job payload — dispatch directly
            dispatch($job);
            return;
        }

        // Log warning: non-tenant-scoped job dispatched
        Log::warning('Non-tenant-scoped job dispatched', ['job' => get_class($job)]);
        dispatch($job);
    }
}

// Usage in service layer
ProcessMediaUploadJob::dispatch(
    mediaUploadId: $mediaUpload->id,
    tenantId: app('tenant.id'),
    actorId: auth()->id(),
    correlationId: request()->header('X-Correlation-Id', (string) Str::uuid()),
    requestId: request()->id(),
);
```

---

## 4. Context Isolation Between Jobs

Workers MUST NOT carry context from one job to the next.

```php
// Worker loop:
while ($job = $queue->pop()) {
    $tenantContext = null; // start fresh
    try {
        $tenantContext = $this->setupTenantContext($job); // establish for THIS job
        $job->handle(...);
    } catch (Throwable $e) {
        $this->handleFailure($job, $e, $tenantContext);
    } finally {
        $this->clearTenantContext(); // clear BEFORE next job
    }
}
```

### Context Leakage Risk Scenarios

| Scenario | Risk | Mitigation |
|----------|------|-----------|
| Job A exception before clear() | Next job picks up Job A's tenant context | `finally {}` block guarantees clear() |
| Long-running job A + Job B queued | Job B may start before clear() | Single-job-at-a-time per worker process; clear in finally |
| Singleton service caches tenant data | Next job sees cached tenant A data | Use app()->forgetInstance() or cache with tenant_id key |
| Global state in service | Mutable class property holds tenant ref | Services must be stateless or re-resolved per request/job |

---

## 5. Queue Channel Isolation

```
Named queue channels prevent cross-priority interference:

  ledger        — Financial postings (critical, sequential where needed)
  media         — Media processing (slow, resource-intensive)
  notifications — SMS/email/push (fast)
  exports       — Data exports (slow, low priority)
  ai            — AI inference (slow, bursty)
  default       — General background jobs

Each channel can have dedicated workers.
Ledger channel: consider single-worker to prevent parallel double-entry conflicts.
```

---

## 6. Mandatory Cross-Tenant Queue Isolation Tests

```php
test('media job uses tenant context from payload, not ambient state', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $mediaA = MediaUpload::factory()->for($tenantA)->create();

    // Simulate ambient context is tenantB (wrong tenant in scope)
    app()->instance('tenant.id', $tenantB->id);

    $job = new ProcessMediaUploadJob(
        mediaUploadId: $mediaA->id,
        tenantId: $tenantA->id,  // payload carries correct tenant
    );

    $job->handle(app(TenantContextManager::class));

    // After job: context should be CLEARED (not tenantA, not tenantB)
    expect(app()->bound('tenant.id'))->toBeFalse();
});

test('job context is cleared after success', function () {
    $job = createTenantScopedJob(tenantId: $tenant->id);
    $job->handle(app(TenantContextManager::class));
    expect(app(TenantContext::class))->toBeNull();
});

test('job context is cleared after failure', function () {
    $job = createFailingTenantScopedJob(tenantId: $tenant->id);
    try { $job->handle(app(TenantContextManager::class)); } catch (\Throwable) {}
    expect(app(TenantContext::class))->toBeNull();
});

test('job for tenant A cannot access tenant B data', function () {
    $jobA = createTenantScopedJob(tenantId: $tenantA->id);
    $resourceB = Resource::factory()->for($tenantB)->create();

    expect(fn() => $jobA->accessResource($resourceB->id))->toThrow(AuthorizationException::class);
});
```

---

## 7. Scheduled Task Tenant Safety

```php
// Scheduled tasks iterate over tenants explicitly
class DailyBillingScheduledJob implements ShouldQueue
{
    public function handle(): void
    {
        $manager = app(TenantContextManager::class);

        Tenant::active()->each(function (Tenant $tenant) use ($manager) {
            try {
                $manager->resolveForSchedule($tenant->id);
                app(BillingService::class)->processDailyBilling($tenant->id);
            } catch (Throwable $e) {
                Log::error('Billing failed for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage()
                ]);
                // One tenant failure does NOT stop other tenants
            } finally {
                $manager->clear(); // Clear between each tenant
            }
        });
    }
}
```

---

## 8. Queue Job Audit Requirements

Every job that performs a privileged mutation MUST record audit evidence:

```php
AuditLog::record(
    event: 'MEDIA_PROCESSED',
    tenantId: app('tenant.id'),
    actorType: 'QUEUE_WORKER',
    actorId: $this->actorId,
    correlationId: $this->correlationId,
    resource: ['type' => 'media_upload', 'id' => $this->mediaUploadId],
);
```

---

*Document Owner: DevOps Architect + Security Architect | v1.0.0 | Invariants: I31, I32*
