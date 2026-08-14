<?php

declare(strict_types=1);

namespace App\Domain\Registration\Policies;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Enforces account lifecycle access rules independent of IAM permissions.
 */
class AccountAccessPolicy
{
    /**
     * Determine if the user is permitted to authenticate.
     * 
     * @throws ValidationException if the account is in a terminal deny state.
     */
    public function ensureCanAuthenticate(User $user): void
    {
        if ($user->account_status === null || ! $user->account_status->mayAuthenticate()) {
            throw ValidationException::withMessages([
                // We use a generic message to prevent enumeration, or if business
                // requires, a specific message. We'll use auth.failed by default
                // to not disclose suspension details on public endpoints unless required.
                'identifier' => __('auth.failed'),
            ]);
        }
    }

    /**
     * Check if user has limited onboarding access.
     */
    public function isOnboarding(User $user): bool
    {
        return ! $user->account_status->isActive();
    }

    /**
     * Determine if the user is permitted to access operational workspaces.
     */
    public function mayAccessWorkspace(User $user): bool
    {
        return $user->account_status !== null && $user->account_status->isActive();
    }

    /**
     * Ensure the user is permitted to access operational workspaces.
     *
     * @throws ValidationException if the account is not in ACTIVE state.
     */
    public function ensureCanAccessWorkspace(User $user): void
    {
        if (! $this->mayAccessWorkspace($user)) {
            // Use a 403-equivalent rejection for workspace boundaries.
            throw ValidationException::withMessages([
                'workspace' => __('This account is not eligible for operational workspace access.'),
            ])->status(403);
        }
    }
}
