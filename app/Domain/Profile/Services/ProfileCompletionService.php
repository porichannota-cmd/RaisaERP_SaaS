<?php

declare(strict_types=1);

namespace App\Domain\Profile\Services;

use App\Domain\Registration\Enums\AccountStatus;
use App\Models\ProfileSectionStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProfileCompletionService
{
    public function recalculate(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Transition from MOBILE_VERIFIED to PROFILE_INCOMPLETE upon profile actions
            if ($user->account_status === AccountStatus::MOBILE_VERIFIED) {
                $user->update(['account_status' => AccountStatus::PROFILE_INCOMPLETE]);
            }

            // In a fuller implementation, calculate percentages across Sections.
        });
    }

    /**
     * Checks if all required Wave 2D base profile sections are marked COMPLETE.
     */
    public function isBaseProfileComplete(User $user): bool
    {
        $requiredSections = ['PERSONAL', 'CONTACT', 'ADDRESS'];

        $completedCount = ProfileSectionStatus::where('user_id', $user->id)
            ->whereIn('section', $requiredSections)
            ->where('status', 'COMPLETE')
            ->count();

        return $completedCount === count($requiredSections);
    }
}
