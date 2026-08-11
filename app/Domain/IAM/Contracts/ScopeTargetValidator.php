<?php

namespace App\Domain\IAM\Contracts;

use App\Domain\IAM\Enums\AuthScope;

interface ScopeTargetValidator
{
    /**
     * Validate that the scope_id belongs to the given tenant.
     * Returns true if valid, false if invalid/foreign tenant,
     * or throws an exception if the scope type is unsupported/unregistered.
     */
    public function validate(AuthScope $scopeType, ?string $scopeId, ?string $tenantId): bool;
}
