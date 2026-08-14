<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Registration\Enums\AccountStatus;
use App\Models\AccountReviewDecision;
use App\Models\AccountReviewRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountReviewService
{
    public function __construct(
        private readonly AccountLifecycleService $lifecycleService,
        private readonly \App\Domain\Profile\Services\ProfileCompletionService $profileCompletionService
    ) {}

    /**
     * Submit an account for review.
     */
    public function requestReview(User $user): AccountReviewRequest
    {
        $this->ensurePrerequisites($user);

        return DB::transaction(function () use ($user) {
            $existing = AccountReviewRequest::where('user_id', $user->id)
                ->where('status', 'PENDING')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $request = AccountReviewRequest::create([
                'user_id' => $user->id,
                'status' => 'PENDING',
            ]);

            if ($user->account_status === AccountStatus::REJECTED) {
                $this->lifecycleService->resubmitForReview($user);
            } else {
                $this->lifecycleService->submitForReview($user);
            }

            return $request;
        });
    }

    private function ensurePrerequisites(User $user): void
    {
        if (!in_array($user->account_status, [AccountStatus::PROFILE_INCOMPLETE, AccountStatus::REJECTED], true)) {
             throw ValidationException::withMessages([
                'user' => 'User must be in PROFILE_INCOMPLETE or REJECTED state to request approval.',
            ]);
        }

        if (!$this->profileCompletionService->isBaseProfileComplete($user)) {
             throw ValidationException::withMessages([
                'user' => 'Required base profile sections are not fully complete.',
            ]);
        }

        $verification = \App\Models\UserIdentityVerification::where('user_id', $user->id)->latest()->first();
        if (!$verification || $verification->status->value !== 'VERIFIED') {
             throw ValidationException::withMessages([
                'user' => 'Identity is not verified.',
            ]);
        }
    }

    /**
     * Process an approval decision.
     */
    public function approve(AccountReviewRequest $request, User $reviewer): AccountReviewDecision
    {
        return DB::transaction(function () use ($request, $reviewer) {
            $request = AccountReviewRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'PENDING') {
                throw ValidationException::withMessages(['request' => 'Review request is not pending.']);
            }

            $decision = AccountReviewDecision::create([
                'account_review_request_id' => $request->id,
                'reviewer_id' => $reviewer->id,
                'decision' => 'APPROVE',
            ]);

            $request->update([
                'status' => 'APPROVED',
                'resolved_at' => now(),
            ]);

            $this->lifecycleService->approve($request->user, $reviewer);

            return $decision;
        });
    }

    /**
     * Process a rejection decision.
     */
    public function reject(AccountReviewRequest $request, User $reviewer, string $reason): AccountReviewDecision
    {
        return DB::transaction(function () use ($request, $reviewer, $reason) {
            $request = AccountReviewRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'PENDING') {
                throw ValidationException::withMessages(['request' => 'Review request is not pending.']);
            }

            if (mb_strlen($reason) > 1000) {
                 throw ValidationException::withMessages(['reason' => 'Rejection reason is too long.']);
            }

            $decision = AccountReviewDecision::create([
                'account_review_request_id' => $request->id,
                'reviewer_id' => $reviewer->id,
                'decision' => 'REJECT',
                'reason' => $reason,
            ]);

            $request->update([
                'status' => 'REJECTED',
                'resolved_at' => now(),
            ]);

            $this->lifecycleService->reject($request->user, $reviewer, $reason);

            return $decision;
        });
    }
}
