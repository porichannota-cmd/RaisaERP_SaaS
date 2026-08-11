<?php

namespace Tests\Unit\IAM;

use App\Domain\IAM\Enums\AuthScope;
use App\Domain\IAM\Services\DefaultScopeTargetValidator;
use Tests\TestCase;

class ScopeTargetValidatorTest extends TestCase
{
    public function test_tenant_id_less_scopes_behave_according_to_semantics()
    {
        $validator = new DefaultScopeTargetValidator;

        $this->assertTrue($validator->validate(AuthScope::TENANT, null, 'tenant-123'));
        $this->assertTrue($validator->validate(AuthScope::PLATFORM, null, null));
        $this->assertTrue($validator->validate(AuthScope::OWN, null, 'tenant-123'));
    }

    public function test_missing_required_scope_id_fails()
    {
        $validator = new DefaultScopeTargetValidator;

        // BRANCH requires ID
        $this->assertFalse($validator->validate(AuthScope::BRANCH, null, 'tenant-123'));
        $this->assertFalse($validator->validate(AuthScope::BRANCH, '', 'tenant-123'));
    }

    public function test_unknown_unregistered_resource_fails_closed()
    {
        $validator = new DefaultScopeTargetValidator;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope target validator not registered');

        $validator->validate(AuthScope::BRANCH, 'branch-123', 'tenant-123');
    }

    public function test_foreign_tenant_target_denied_when_registered()
    {
        $validator = new DefaultScopeTargetValidator;

        $validator->register(AuthScope::BRANCH, function (string $scopeId, ?string $tenantId) {
            // Mock DB check
            $db = [
                'branch-A' => 'tenant-1',
                'branch-B' => 'tenant-2',
            ];

            return ($db[$scopeId] ?? null) === $tenantId;
        });

        // Valid
        $this->assertTrue($validator->validate(AuthScope::BRANCH, 'branch-A', 'tenant-1'));

        // Foreign tenant
        $this->assertFalse($validator->validate(AuthScope::BRANCH, 'branch-B', 'tenant-1'));
    }
}
