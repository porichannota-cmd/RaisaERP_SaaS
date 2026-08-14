<?php

declare(strict_types=1);

namespace App\Domain\Profile\Services;

use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use App\Domain\Registration\Contracts\SensitiveLookupHasherInterface;
use App\Models\User;
use App\Models\UserMfsAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserMfsAccountService
{
    public function __construct(
        private readonly SensitiveDataCipherInterface $cipher,
        private readonly SensitiveLookupHasherInterface $hasher,
        private readonly ProfileCompletionService $completionService
    ) {}

    public function addAccount(User $user, array $data): UserMfsAccount
    {
        return DB::transaction(function () use ($user, $data) {
            $fingerprint = $this->hasher->hash($data['mobile_number']);

            // Check for duplicate per user
            if (UserMfsAccount::where('user_id', $user->id)->where('provider', $data['provider'])->where('mobile_fingerprint', $fingerprint)->exists()) {
                throw ValidationException::withMessages(['mobile_number' => 'This MFS account is already added to your profile.']);
            }

            if (!empty($data['is_primary'])) {
                $this->demotePrimary($user);
            }

            $encrypted = $this->cipher->encrypt($data['mobile_number']);

            $account = UserMfsAccount::create([
                'user_id' => $user->id,
                'provider' => $data['provider'],
                'mobile_encrypted' => $encrypted,
                'mobile_fingerprint' => $fingerprint,
                'account_name' => $data['account_name'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
                'verification_status' => 'pending',
            ]);

            $this->completionService->recalculate($user);

            return $account;
        });
    }

    public function updateAccount(User $user, string $accountId, array $data): UserMfsAccount
    {
        return DB::transaction(function () use ($user, $accountId, $data) {
            $account = UserMfsAccount::where('user_id', $user->id)->where('id', $accountId)->firstOrFail();

            if (!empty($data['is_primary']) && !$account->is_primary) {
                $this->demotePrimary($user);
            }

            if (!empty($data['mobile_number'])) {
                $provider = $data['provider'] ?? $account->provider;
                $fingerprint = $this->hasher->hash($data['mobile_number']);

                if (UserMfsAccount::where('user_id', $user->id)
                    ->where('provider', $provider)
                    ->where('mobile_fingerprint', $fingerprint)
                    ->where('id', '!=', $accountId)->exists()) {
                    throw ValidationException::withMessages(['mobile_number' => 'This MFS account is already added to your profile.']);
                }

                $data['mobile_encrypted'] = $this->cipher->encrypt($data['mobile_number']);
                $data['mobile_fingerprint'] = $fingerprint;
            }

            unset($data['mobile_number']);

            $account->update($data);

            $this->completionService->recalculate($user);

            return $account;
        });
    }

    public function deleteAccount(User $user, string $accountId): void
    {
        UserMfsAccount::where('user_id', $user->id)->where('id', $accountId)->delete();
        $this->completionService->recalculate($user);
    }

    private function demotePrimary(User $user): void
    {
        UserMfsAccount::where('user_id', $user->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
