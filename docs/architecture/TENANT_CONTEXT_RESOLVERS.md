# RAISA ERP — TENANT CONTEXT RESOLVER MODEL
**Version:** 1.0.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Contract

```php
interface TenantContextResolverContract
{
    /**
     * Resolve TenantContext for the current execution.
     * Returns null if context cannot be determined (caller handles 403/skip).
     */
    public function resolve(mixed $context): ?TenantContext;
}

final class TenantContext
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $userId,         // nullable for SYSTEM/WEBHOOK contexts
        public readonly string $actorType,      // USER, SERVICE_PRINCIPAL, WEBHOOK, SYSTEM, QUEUE_WORKER, SCHEDULE
        public readonly string $membershipId,   // nullable for non-user contexts
        public readonly PermissionSet $permissions,
        public readonly CapabilitySet $capabilities,
        public readonly string $correlationId,
        public readonly ?string $requestId = null,
        public readonly ?string $impersonatorId = null,
    ) {}
}
```

---

## 2. Resolver Adapters

### 2.1 Web Session Resolver (Inertia SPA)

```php
class WebSessionTenantResolver implements TenantContextResolverContract
{
    public function resolve(mixed $context): ?TenantContext
    {
        /** @var Request $context */
        $user = $context->user();
        if (!$user) return null;

        $session = ActiveTenantSession::where('session_id', $context->session()->getId())->first();
        if (!$session) {
            // User authenticated but no active tenant selected — redirect to selector
            return null;
        }

        return $this->buildContext($user, $session->tenant_id, 'USER', $context->correlationId());
    }
}
```

### 2.2 API Token Resolver (Sanctum / Mobile App)

```php
class ApiTokenTenantResolver implements TenantContextResolverContract
{
    public function resolve(mixed $context): ?TenantContext
    {
        /** @var Request $context */
        $user = $context->user(); // Sanctum: loads from personal_access_tokens
        if (!$user) return null;

        // API tokens carry tenant_id in the token's abilities/metadata
        $token = $context->user()->currentAccessToken();
        $tenantId = $token->abilities['tenant_id'] ?? null;
        if (!$tenantId) return null;

        // Verify user has active membership in this tenant
        $membership = TenantMembership::active($user->id, $tenantId)->first();
        if (!$membership) return null;

        return $this->buildContext($user, $tenantId, 'USER', $context->correlationId());
    }
}
```

Token creation for mobile app:
```php
// Token is scoped to a specific tenant at creation
$token = $user->createToken(
    name: 'mobile-app',
    abilities: ['tenant_id' => $selectedTenantId, 'scope' => 'mobile'],
);
```

### 2.3 Service Principal Resolver (internal service-to-service)

```php
class ServicePrincipalTenantResolver implements TenantContextResolverContract
{
    public function resolve(mixed $context): ?TenantContext
    {
        /** @var Request $context */
        $apiKey = $context->header('X-Service-Key');
        $tenantId = $context->header('X-Tenant-Id');
        if (!$apiKey || !$tenantId) return null;

        $principal = ServicePrincipal::verifyKey($apiKey);
        if (!$principal || !$principal->hasAccessToTenant($tenantId)) return null;

        return new TenantContext(
            tenantId: $tenantId,
            userId: $principal->id,
            actorType: 'SERVICE_PRINCIPAL',
            membershipId: '',
            permissions: $principal->permissions(),
            capabilities: $this->capabilities->resolve($tenantId),
            correlationId: $context->correlationId(),
        );
    }
}
```

### 2.4 Webhook Resolver

```php
class WebhookTenantResolver implements TenantContextResolverContract
{
    public function resolve(mixed $context): ?TenantContext
    {
        /** @var Request $context */
        // Webhook routes embed tenant in URL or carry provider reference
        $tenantId = $context->route('tenantId')
                    ?? $this->lookupTenantFromProviderRef($context->input('provider_ref'));

        if (!$tenantId) return null;

        // Signature already verified by WebhookSignatureMiddleware
        return new TenantContext(
            tenantId: $tenantId,
            userId: 'webhook',
            actorType: 'WEBHOOK',
            membershipId: '',
            permissions: PermissionSet::webhookSet(),
            capabilities: $this->capabilities->resolve($tenantId),
            correlationId: $context->header('X-Webhook-Delivery') ?? Str::uuid(),
        );
    }
}
```

### 2.5 Queue Job Resolver

```php
class QueueJobTenantResolver implements TenantContextResolverContract
{
    public function resolve(mixed $context): ?TenantContext
    {
        /** @var array{tenant_id: string, actor_id: ?string, ...} $context */
        $tenantId = $context['tenant_id'] ?? null;
        $actorId  = $context['actor_id'] ?? null;
        if (!$tenantId) return null;

        return new TenantContext(
            tenantId: $tenantId,
            userId: $actorId ?? 'system',
            actorType: 'QUEUE_WORKER',
            membershipId: '',
            permissions: PermissionSet::workerSet(),
            capabilities: $this->capabilities->resolve($tenantId),
            correlationId: $context['correlation_id'] ?? Str::uuid(),
            requestId: $context['request_id'] ?? null,
        );
    }
}
```

### 2.6 Scheduled Task Resolver

```php
class ScheduledTaskTenantResolver implements TenantContextResolverContract
{
    /**
     * For scheduled tasks that run across ALL tenants (e.g., backup, health check):
     * Context is established per-tenant in a loop within the job.
     */
    public function resolve(mixed $context): ?TenantContext
    {
        /** @var string $context — tenantId passed explicitly */
        return new TenantContext(
            tenantId: $context,
            userId: 'scheduler',
            actorType: 'SCHEDULED_JOB',
            membershipId: '',
            permissions: PermissionSet::schedulerSet(),
            capabilities: $this->capabilities->resolve($context),
            correlationId: Str::uuid(),
        );
    }
}
```

### 2.7 CLI / Maintenance Resolver

```php
class CliTenantResolver implements TenantContextResolverContract
{
    public function resolve(mixed $context): ?TenantContext
    {
        /** @var string $tenantId — explicit, from Artisan option */
        $tenantId = $context; // e.g., from: php artisan tenant:maintenance --tenant=xxx

        return new TenantContext(
            tenantId: $tenantId,
            userId: 'cli',
            actorType: 'SYSTEM',
            membershipId: '',
            permissions: PermissionSet::systemSet(),
            capabilities: $this->capabilities->resolve($tenantId),
            correlationId: Str::uuid(),
        );
    }
}
```

### 2.8 Super Admin Cross-Tenant Resolver

```php
class SuperAdminCrossTenantResolver implements TenantContextResolverContract
{
    public function resolve(mixed $context): ?TenantContext
    {
        ['user' => $saUser, 'target_tenant_id' => $tenantId, 'reason' => $reason] = $context;

        abort_unless($saUser->isSuperAdmin(), 403);
        abort_unless(Tenant::exists($tenantId), 404);

        AuditLog::record('SA_CROSS_TENANT_ACCESS', [
            'sa_user_id' => $saUser->id, 'target_tenant_id' => $tenantId, 'reason' => $reason
        ]);

        return new TenantContext(
            tenantId: $tenantId,
            userId: $saUser->id,
            actorType: 'PLATFORM_ADMIN',
            membershipId: '',
            permissions: PermissionSet::platformAdminSet(),
            capabilities: $this->capabilities->resolve($tenantId),
            correlationId: Str::uuid(),
            impersonatorId: $saUser->id,
        );
    }
}
```

---

## 3. Resolver Chain (TenantContextManager)

```php
class TenantContextManager
{
    private array $resolvers = [
        'web'           => WebSessionTenantResolver::class,
        'api_token'     => ApiTokenTenantResolver::class,
        'service'       => ServicePrincipalTenantResolver::class,
        'webhook'       => WebhookTenantResolver::class,
        'queue'         => QueueJobTenantResolver::class,
        'schedule'      => ScheduledTaskTenantResolver::class,
        'cli'           => CliTenantResolver::class,
        'sa_cross'      => SuperAdminCrossTenantResolver::class,
    ];

    public function resolveForRequest(Request $request): void
    {
        foreach ($this->resolvers as $key => $resolverClass) {
            $resolver = app($resolverClass);
            $context = $resolver->resolve($request);
            if ($context) {
                app()->instance(TenantContext::class, $context);
                app()->instance('tenant.id', $context->tenantId);
                return;
            }
        }
        // No resolver matched — tenant context not established
        app()->instance(TenantContext::class, null);
    }

    public function resolveForJob(array $jobPayload): void
    {
        $resolver = app(QueueJobTenantResolver::class);
        $context = $resolver->resolve($jobPayload);
        app()->instance(TenantContext::class, $context);
        app()->instance('tenant.id', $context?->tenantId);
    }

    public function clear(): void
    {
        app()->forgetInstance(TenantContext::class);
        app()->forgetInstance('tenant.id');
    }
}
```

---

## 4. Browser Tenant Switch

The browser may REQUEST a tenant switch (not impose one):

```
POST /api/v1/auth/tenant/switch { "tenant_id": "..." }

Server:
  1. Verify active session (user authenticated)
  2. Verify user has ACTIVE membership in the requested tenant
  3. Verify tenant is ACTIVE (not suspended/churned)
  4. Update active_tenant_sessions for this session
  5. Audit: TENANT_SWITCHED
  6. Return: new capability set + menu
```

The browser `tenant_id` is used ONLY to initiate a switch request.
The server verifies membership and sets context. Browser cannot impose context.

---

## 5. Context Leakage Prevention

```php
// Every middleware establishes context at start, clears in finally
class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->manager->resolveForRequest($request);
            return $next($request);
        } finally {
            $this->manager->clear(); // Always clear, even on exception
        }
    }
}

// Queue worker: establish context per job, clear in finally
class TenantAwareJobMiddleware implements Middleware
{
    public function handle(mixed $job, Closure $next): void
    {
        try {
            $this->manager->resolveForJob($job->getTenantPayload());
            $next($job);
        } finally {
            $this->manager->clear();
        }
    }
}
```

---

*Document Owner: Principal Architect | v1.0.0 | Invariants: I18, I31, I32*
