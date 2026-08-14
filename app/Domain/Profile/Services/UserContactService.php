<?php

declare(strict_types=1);

namespace App\Domain\Profile\Services;

use App\Models\User;
use App\Models\UserContactDetail;
use Illuminate\Support\Facades\DB;

class UserContactService
{
    public function __construct(private readonly ProfileCompletionService $completionService) {}

    public function updateContactDetails(User $user, array $data): UserContactDetail
    {
        return DB::transaction(function () use ($user, $data) {
            $contact = UserContactDetail::firstOrCreate(['user_id' => $user->id]);
            $contact->update($data);

            $this->completionService->recalculate($user);

            return $contact;
        });
    }
}
