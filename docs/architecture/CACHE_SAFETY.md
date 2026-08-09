# RAISA ERP — TENANT-SCOPED CACHE SAFETY
**Version:** 1.0.0 | **Date:** 2026-08-09 | **Phase:** 00B

---

## 1. Principle

Any cache key derived from tenant-specific data MUST include the tenant identity.
Shared cache keys MUST NEVER be used for tenant-specific data.

---

## 2. Canonical Cache Key Scheme

```
{prefix}:{tenantId}:{resource}:{qualifier}

Examples:
  tenant:{tenantId}:capabilities:v1
  tenant:{tenantId}:permissions:{userId}
  tenant:{tenantId}:product:{productId}
  tenant:{tenantId}:pricing:{priceListId}
  tenant:{tenantId}:settings:general
  tenant:{tenantId}:branch:{branchId}:config

Platform-level (no tenant):
  platform:modules:registry
  platform:currencies:all
  platform:business_types:all
```

---

## 3. Forbidden Patterns

```php
// FORBIDDEN: no tenant in key
Cache::put('capabilities', $capabilities, 300);
Cache::put('user_permissions', $permissions, 300);
Cache::put('products', $products, 300);
Cache::put('pricing', $pricingList, 300);

// CORRECT: tenant in key
Cache::put("tenant:{$tenantId}:capabilities:v1", $capabilities, 300);
Cache::put("tenant:{$tenantId}:permissions:{$userId}", $permissions, 300);
Cache::put("tenant:{$tenantId}:products:page:{$page}", $products, 60);
Cache::put("tenant:{$tenantId}:pricing:{$priceListId}", $pricingList, 300);
```

---

## 4. CacheKeyBuilder Service

```php
class TenantCacheKey
{
    public static function make(string ...$parts): string
    {
        $tenantId = app()->bound('tenant.id') ? app('tenant.id') : 'global';
        return implode(':', ['tenant', $tenantId, ...$parts]);
    }

    public static function global(string ...$parts): string
    {
        return implode(':', ['platform', ...$parts]);
    }
}

// Usage:
Cache::remember(TenantCacheKey::make('capabilities', 'v1'), 300, fn() => ...);
Cache::remember(TenantCacheKey::make('permissions', $userId), 300, fn() => ...);
Cache::remember(TenantCacheKey::global('modules', 'registry'), 3600, fn() => ...);
```

---

## 5. Cache Invalidation Triggers

| Trigger | Invalidate |
|---------|-----------|
| Tenant switch (new active tenant) | `tenant:{newTenantId}:*` re-warm on next access |
| Permission/grant change for user | `tenant:{tenantId}:permissions:{userId}` |
| Subscription plan change | `tenant:{tenantId}:capabilities:*` |
| Capability override change | `tenant:{tenantId}:capabilities:*` |
| Module enable/disable | `tenant:{tenantId}:capabilities:*`, `tenant:{tenantId}:menu:*` |
| Product update | `tenant:{tenantId}:product:{productId}`, `tenant:{tenantId}:products:*` |
| Price list update | `tenant:{tenantId}:pricing:{priceListId}` |
| Settings change | `tenant:{tenantId}:settings:*` |
| Membership revocation | `tenant:{tenantId}:permissions:{userId}` (delete immediately) |

### Invalidation Patterns

```php
class TenantCacheInvalidator
{
    public function onPermissionChanged(string $tenantId, string $userId): void
    {
        Cache::forget("tenant:{$tenantId}:permissions:{$userId}");
    }

    public function onCapabilityChanged(string $tenantId): void
    {
        // Laravel cache tags (Redis)
        Cache::tags(["tenant:{$tenantId}:capabilities"])->flush();
    }

    public function onMembershipRevoked(string $tenantId, string $userId): void
    {
        // Immediate invalidation — security critical
        Cache::forget("tenant:{$tenantId}:permissions:{$userId}");
        Cache::forget("tenant:{$tenantId}:capabilities:{$userId}");
        // Remove active tenant session
        ActiveTenantSession::where('user_id', $userId)
            ->whereHas('membership', fn($q) => $q->where('tenant_id', $tenantId))
            ->delete();
    }

    public function onTenantSuspended(string $tenantId): void
    {
        // Flush all cached tenant data
        Cache::tags(["tenant:{$tenantId}"])->flush();
        // Remove all active sessions for this tenant
        ActiveTenantSession::where('tenant_id', $tenantId)->delete();
    }
}
```

---

## 6. TTL Guidelines

| Cache Type | TTL |
|-----------|-----|
| Capability set | 5 minutes (short — capability changes should propagate fast) |
| User permission set | 5 minutes |
| Product data | 5 minutes |
| Pricing | 5 minutes |
| Tenant settings | 10 minutes |
| Branch/department config | 10 minutes |
| Platform module registry | 60 minutes (changes via deployment only) |
| Currency metadata | 24 hours (changes very rarely) |
| Business types | 24 hours |

---

## 7. Mandatory Cache Isolation Tests

```php
test('tenant A cache is not accessible by tenant B', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    // Cache some data for tenant A
    Cache::put("tenant:{$tenantA->id}:settings:general", ['name' => 'Alpha Corp'], 300);

    // Tenant B should not see tenant A's settings
    $tenantBSettings = Cache::get("tenant:{$tenantB->id}:settings:general");
    expect($tenantBSettings)->toBeNull();
});

test('capability cache is invalidated on module disable', function () {
    $cachedCapabilities = [...]; // warm cache
    Cache::put("tenant:{$tenantId}:capabilities:v1", $cachedCapabilities, 300);

    // Disable a module
    $invalidator->onCapabilityChanged($tenantId);

    $afterInvalidation = Cache::get("tenant:{$tenantId}:capabilities:v1");
    expect($afterInvalidation)->toBeNull();
});

test('permission cache is invalidated on membership revocation', function () {
    Cache::put("tenant:{$tenantId}:permissions:{$userId}", $permissions, 300);

    $invalidator->onMembershipRevoked($tenantId, $userId);

    expect(Cache::get("tenant:{$tenantId}:permissions:{$userId}"))->toBeNull();
});
```

---

*Document Owner: Principal Architect | v1.0.0 | Invariant: I34*
