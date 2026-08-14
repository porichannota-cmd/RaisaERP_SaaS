<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Models\PlatformReviewerAssignment;
use App\Models\User;

/**
 * Manages authorization for Platform-level account review capabilities.
 * 
 * PA-2F-11: This is a PLATFORM-GLOBAL and PRE-TENANT authorization boundary.
 * It is backed by the explicit platform_reviewer_assignments table and fails closed.
 */
class PlatformAccountReviewAuthorizer
{
    private const CAPABILITY = 'ACCOUNT_REVIEW';
    private const STATUS_ACTIVE = 'ACTIVE';

    /**
     * Determine if a user has the ACTIVE platform assignment for account review.
     */
    private function hasAssignment(User $user): bool
    {
        return PlatformReviewerAssignment::where('user_id', $user->id)
            ->where('capability', self::CAPABILITY)
            ->where('status', self::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Can the user view the account review queue?
     */
    public function mayViewQueue(User $user): bool
    {
        return $this->hasAssignment($user) && $user->account_status->mayAuthenticate();
    }

    /**
     * Can the user explicitly approve an account?
     */
    public function mayApprove(User $user): bool
    {
        return $this->hasAssignment($user) && $user->account_status->mayAuthenticate();
    }

    /**
     * Can the user explicitly reject an account?
     */
    public function mayReject(User $user): bool
    {
        return $this->hasAssignment($user) && $user->account_status->mayAuthenticate();
    }
}
