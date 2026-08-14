<?php

declare(strict_types=1);

namespace App\Domain\Profile\Services;

use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use App\Domain\Registration\Contracts\SensitiveLookupHasherInterface;
use App\Models\User;
use App\Models\UserBankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserBankAccountService
{
    public function __construct(
        private readonly SensitiveDataCipherInterface $cipher,
        private readonly SensitiveLookupHasherInterface $hasher,
        private readonly ProfileCompletionService $completionService
    ) {}

    public function addAccount(User $user, array $data): UserBankAccount
    {
        return DB::transaction(function () use ($user, $data) {
            $fingerprint = $this->hasher->hash($data['account_number']);

            // Check for duplicate per user (PA-2D-02)
            if (UserBankAccount::where('user_id', $user->id)->where('account_number_fingerprint', $fingerprint)->exists()) {
                throw ValidationException::withMessages(['account_number' => 'This bank account is already added to your profile.']);
            }

            if (!empty($data['is_primary'])) {
                $this->demotePrimary($user);
            }

            $encrypted = $this->cipher->encrypt($data['account_number']);

            $account = UserBankAccount::create([
                'user_id' => $user->id,
                'bank_name' => $data['bank_name'],
                'branch_name' => $data['branch_name'] ?? null,
                'account_holder_name' => $data['account_holder_name'],
                'account_number_encrypted' => $encrypted,
                'account_number_fingerprint' => $fingerprint,
                'routing_number' => $data['routing_number'] ?? null,
                'swift_code' => $data['swift_code'] ?? null,
                'account_type' => $data['account_type'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
                'verification_status' => 'pending',
            ]);

            $this->completionService->recalculate($user);

            return $account;
        });
    }

    public function updateAccount(User $user, string $accountId, array $data): UserBankAccount
    {
        return DB::transaction(function () use ($user, $accountId, $data) {
            $account = UserBankAccount::where('user_id', $user->id)->where('id', $accountId)->firstOrFail();

            if (!empty($data['is_primary']) && !$account->is_primary) {
                $this->demotePrimary($user);
            }

            // Only update account_number if it is provided
            if (!empty($data['account_number'])) {
                $fingerprint = $this->hasher->hash($data['account_number']);
                if (UserBankAccount::where('user_id', $user->id)->where('account_number_fingerprint', $fingerprint)->where('id', '!=', $accountId)->exists()) {
                    throw ValidationException::withMessages(['account_number' => 'This bank account is already added to your profile.']);
                }
                $data['account_number_encrypted'] = $this->cipher->encrypt($data['account_number']);
                $data['account_number_fingerprint'] = $fingerprint;
            }

            unset($data['account_number']); // Ensure plaintext is not mass-assigned accidentally

            $account->update($data);

            $this->completionService->recalculate($user);

            return $account;
        });
    }

    public function deleteAccount(User $user, string $accountId): void
    {
        UserBankAccount::where('user_id', $user->id)->where('id', $accountId)->delete();
        $this->completionService->recalculate($user);
    }

    private function demotePrimary(User $user): void
    {
        UserBankAccount::where('user_id', $user->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
