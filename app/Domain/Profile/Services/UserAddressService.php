<?php

declare(strict_types=1);

namespace App\Domain\Profile\Services;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAddressService
{
    public function __construct(private readonly ProfileCompletionService $completionService) {}

    public function upsertAddress(User $user, string $type, array $data): UserAddress
    {
        return DB::transaction(function () use ($user, $type, $data) {
            $address = UserAddress::updateOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                $data
            );

            $this->completionService->recalculate($user);
            return $address;
        });
    }

    public function copyPresentToPermanent(User $user): UserAddress
    {
        return DB::transaction(function () use ($user) {
            $present = UserAddress::where('user_id', $user->id)
                ->where('type', 'PRESENT')
                ->first();

            if (! $present) {
                throw ValidationException::withMessages(['address' => 'Present address not found.']);
            }

            $data = $present->toArray();
            unset($data['id'], $data['user_id'], $data['type'], $data['created_at'], $data['updated_at']);

            return $this->upsertAddress($user, 'PERMANENT', $data);
        });
    }

    public function deleteAddress(User $user, string $id): void
    {
        UserAddress::where('user_id', $user->id)->where('id', $id)->delete();
        $this->completionService->recalculate($user);
    }
}
