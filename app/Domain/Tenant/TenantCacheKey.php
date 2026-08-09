<?php

namespace App\Domain\Tenant;

class TenantCacheKey
{
    /**
     * Generate a cache key automatically prefixed with the active tenant ID.
     * Prevents cache leakage across tenants.
     */
    public static function make(string $key): string
    {
        $tenantId = ActiveTenantContext::get();
        return "t:{$tenantId}:{$key}";
    }

    /**
     * Generate a cache key for a specific tenant ID, bypassing the active context.
     * Useful for cross-tenant operations or console commands.
     */
    public static function forTenant(string $tenantId, string $key): string
    {
        return "t:{$tenantId}:{$key}";
    }
}
