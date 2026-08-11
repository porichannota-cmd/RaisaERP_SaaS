<?php

namespace App\Domain\IAM\Services;

use App\Domain\IAM\Contracts\ScopeTargetValidator;
use App\Domain\IAM\Enums\AuthScope;
use App\Domain\IAM\Models\AuthorizationGrant;
use App\Domain\IAM\Models\Role;

class AuthorizationGrantService
{
    public function __construct(
        protected ScopeTargetValidator $validator
    ) {}

    public function createGrant(Role $role, string $permissionKey, AuthScope $scopeType, ?string $scopeId = null): AuthorizationGrant
    {
        // For currently resolvable scope types, enforce tenant ownership now.
        // For unresolved future resource types: fail safely or restrict grant creation until a registered validator exists.
        $this->validator->validate($scopeType, $scopeId, $role->tenant_id);

        return AuthorizationGrant::create([
            'role_id' => $role->id,
            'permission_key' => $permissionKey,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'effective_from' => now()->toDateString(),
        ]);
    }
}
