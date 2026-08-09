<?php

namespace App\Domain\Tenant;

use RuntimeException;

class ActiveTenantContext
{
    private static ?string $currentTenantId = null;

    /**
     * Set the current tenant ID. Use cautiously in middleware or job handlers.
     */
    public static function set(string $tenantId): void
    {
        self::$currentTenantId = $tenantId;
    }

    /**
     * Get the current active tenant ID. Throws if no tenant is set (to prevent cross-tenant leakage).
     */
    public static function get(): string
    {
        if (self::$currentTenantId === null) {
            throw new RuntimeException("No active tenant context is set. Ensure middleware or job handler sets the tenant context.");
        }

        return self::$currentTenantId;
    }

    /**
     * Check if a tenant is currently set.
     */
    public static function isSet(): bool
    {
        return self::$currentTenantId !== null;
    }

    /**
     * Clear the current tenant context. Crucial for long-running workers (Octane/Queue).
     */
    public static function clear(): void
    {
        self::$currentTenantId = null;
    }

    /**
     * Run a callback within a specific tenant context, then reliably clear it.
     */
    public static function run(string $tenantId, callable $callback)
    {
        $previousTenantId = self::$currentTenantId;
        self::set($tenantId);

        try {
            return $callback();
        } finally {
            if ($previousTenantId !== null) {
                self::set($previousTenantId);
            } else {
                self::clear();
            }
        }
    }
}
