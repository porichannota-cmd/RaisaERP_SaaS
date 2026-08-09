<?php

namespace Tests\Unit\Tenant;

use App\Domain\Tenant\ActiveTenantContext;
use App\Domain\Tenant\TenantCacheKey;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TenantContextTest extends TestCase
{
    protected function tearDown(): void
    {
        ActiveTenantContext::clear();
        parent::tearDown();
    }

    public function test_it_sets_and_gets_tenant_context()
    {
        ActiveTenantContext::set('TENANT-123');
        $this->assertTrue(ActiveTenantContext::isSet());
        $this->assertEquals('TENANT-123', ActiveTenantContext::get());
    }

    public function test_it_throws_if_accessed_without_being_set()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active tenant context is set');
        ActiveTenantContext::get();
    }

    public function test_it_clears_context()
    {
        ActiveTenantContext::set('TENANT-123');
        ActiveTenantContext::clear();
        $this->assertFalse(ActiveTenantContext::isSet());
    }

    public function test_run_callback_in_context_and_restore()
    {
        ActiveTenantContext::set('TENANT-OLD');
        
        $result = ActiveTenantContext::run('TENANT-NEW', function () {
            $this->assertEquals('TENANT-NEW', ActiveTenantContext::get());
            return 'done';
        });

        $this->assertEquals('done', $result);
        $this->assertEquals('TENANT-OLD', ActiveTenantContext::get());
    }

    public function test_run_callback_clears_if_previously_null()
    {
        ActiveTenantContext::run('TENANT-NEW', function () {
            $this->assertEquals('TENANT-NEW', ActiveTenantContext::get());
        });

        $this->assertFalse(ActiveTenantContext::isSet());
    }

    public function test_tenant_cache_key_generation()
    {
        ActiveTenantContext::set('TNT-888');
        $key = TenantCacheKey::make('settings_cache');
        
        $this->assertEquals('t:TNT-888:settings_cache', $key);
    }
}
