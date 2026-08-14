<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Registration\Enums\AccountStatus;
use App\Models\AccountStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountLifecycleService
{
    /**
     * Submit an account for review (from PROFILE_INCOMPLETE to PENDING_APPROVAL).
     */
    public function submitForReview(User $user): void
    {
        $this->enforceTransition($user, AccountStatus::PROFILE_INCOMPLETE, AccountStatus::PENDING_APPROVAL);
    }

    /**
     * Resubmit a rejected account for review (from REJECTED to PENDING_APPROVAL).
     */
    public function resubmitForReview(User $user): void
    {
        $this->enforceTransition($user, AccountStatus::REJECTED, AccountStatus::PENDING_APPROVAL);
    }

    /**
     * Approve a pending account (from PENDING_APPROVAL to ACTIVE).
     */
    public function approve(User $user, User $actor): void
    {
        $this->enforceTransition($user, AccountStatus::PENDING_APPROVAL, AccountStatus::ACTIVE, $actor);
    }

    /**
     * Reject a pending account (from PENDING_APPROVAL to REJECTED).
     */
    public function reject(User $user, User $actor, string $reason): void
    {
        if (empty(trim($reason))) {
            throw ValidationException::withMessages(['reason' => 'A rejection reason is required.']);
        }
        $this->enforceTransition($user, AccountStatus::PENDING_APPROVAL, AccountStatus::REJECTED, $actor, $reason);
    }

    /**
     * Internal transition engine enforcing edges.
     */
    private function enforceTransition(User $user, AccountStatus $expectedCurrent, AccountStatus $newStatus, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($user, $expectedCurrent, $newStatus, $actor, $reason) {
            $currentStatus = $user->account_status;

            if ($currentStatus === $newStatus) {
                return; // Idempotent
            }

            if ($currentStatus !== $expectedCurrent) {
                throw ValidationException::withMessages([
                    'status' => "Invalid transition from {$currentStatus->value} to {$newStatus->value}."
                ]);
            }

            AccountStatusHistory::create([
                'user_id' => $user->id,
                'previous_status' => $currentStatus?->value,
                'new_status' => $newStatus->value,
                'actor_id' => $actor?->id,
                'reason' => $reason,
            ]);

            $user->account_status = $newStatus;
            $user->save();
        });
    }
}
