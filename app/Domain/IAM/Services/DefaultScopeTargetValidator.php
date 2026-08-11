<?php

namespace App\Domain\IAM\Services;

use App\Domain\IAM\Contracts\ScopeTargetValidator;
use App\Domain\IAM\Enums\AuthScope;

class DefaultScopeTargetValidator implements ScopeTargetValidator
{
    /**
     * @var array<string, callable>
     */
    protected array $resolvers = [];

    public function __construct()
    {
        // For currently resolvable scope types, enforce tenant ownership now.
        // e.g., if we had a Branch model:
        // $this->register(AuthScope::BRANCH, function(string $scopeId, string $tenantId) {
        //     return Branch::where('id', $scopeId)->where('tenant_id', $tenantId)->exists();
        // });
    }

    public function register(AuthScope $scope, callable $resolver): void
    {
        $this->resolvers[$scope->value] = $resolver;
    }

    public function validate(AuthScope $scopeType, ?string $scopeId, ?string $tenantId): bool
    {
        if (! $scopeType->requiresScopeId()) {
            // For PLATFORM, TENANT, OWN, no ID validation is necessary beyond basic enum logic.
            // (e.g. OWN means "own ID", which is checked at runtime by the policy, not at grant time)
            return true;
        }

        if (empty($scopeId)) {
            return false; // ID is required but missing
        }

        if (! isset($this->resolvers[$scopeType->value])) {
            // "For unresolved future resource types: fail safely or restrict grant creation until a registered validator exists."
            throw new \InvalidArgumentException("Scope target validator not registered for scope type: {$scopeType->value}");
        }

        return call_user_func($this->resolvers[$scopeType->value], $scopeId, $tenantId);
    }
}
