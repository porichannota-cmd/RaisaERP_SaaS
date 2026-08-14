<?php

declare(strict_types=1);

namespace App\Domain\Profile\Services;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;

class UserProfileService
{
    public function __construct(private readonly ProfileCompletionService $completionService) {}

    public function updatePersonalProfile(User $user, array $data): UserProfile
    {
        return DB::transaction(function () use ($user, $data) {
            $profile = UserProfile::firstOrCreate(['user_id' => $user->id]);
            $profile->update($data);

            $this->completionService->recalculate($user);

            return $profile;
        });
    }
}
