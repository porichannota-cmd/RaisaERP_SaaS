<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Registration\Enums\AccountStatus;
use App\Domain\Registration\Enums\RegistrationSessionStatus;
use App\Models\RegistrationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegistrationAccountService
{
    public function __construct(
        private readonly RegistrationSessionTokenService $tokenService,
        private readonly EnterpriseUserIdGenerator $idGenerator,
        private readonly RegistrationIdentityDocumentClaimService $claimService
    ) {}

    /**
     * Transactionally creates a User account from an OTP_VERIFIED session.
     */
    public function createAccount(
        string $publicReference,
        string $token,
        string $password,
        ?string $email = null,
        ?string $name = null
    ): User {
        return DB::transaction(function () use ($publicReference, $token, $password, $email, $name) {
            // Lock the session for update to prevent concurrent double-registration
            $session = RegistrationSession::where('public_reference', $publicReference)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw ValidationException::withMessages(['session' => 'Registration session not found.']);
            }

            if (! $this->tokenService->verify($token, $session->token_hash)) {
                throw ValidationException::withMessages(['token' => 'Invalid registration token.']);
            }

            if ($session->isExpired()) {
                throw ValidationException::withMessages(['session' => 'Registration session has expired.']);
            }

            if ($session->isConsumed()) {
                // Idempotency / replay protection check
                throw ValidationException::withMessages(['session' => 'Registration session is already consumed.']);
            }

            // The session must be in OTP_VERIFIED (or READY_FOR_ACCOUNT_CREATION if there were other stages)
            if (! $session->isVerified()) {
                throw ValidationException::withMessages(['session' => 'Mobile number is not verified.']);
            }

            // Enforce Unique Mobile constraint proactively (DB will also enforce it)
            if (User::where('mobile_canonical', $session->mobile_canonical)->exists()) {
                throw ValidationException::withMessages(['mobile_canonical' => 'Mobile number is already registered.']);
            }

            // Enforce Unique Email if provided
            if ($email !== null && User::where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Email is already registered.']);
            }

            // Generate the secure enterprise ID
            $enterpriseId = $this->idGenerator->generate();

            // Create user securely, bypassing fillable restrictions as this is a controlled internal service boundary
            $user = new User;
            $user->forceFill([
                'enterprise_user_id' => $enterpriseId,
                'mobile_canonical' => $session->mobile_canonical,
                'email' => $email,
                'password' => Hash::make($password),
                'account_status' => AccountStatus::MOBILE_VERIFIED,
                'registration_source' => $session->registration_source,
                'mobile_verified_at' => $session->otp_verified_at,
                // The name is not in Stage 1 requirements directly, but we can set a placeholder or leave null if nullable.
                // Assuming `name` is required by the original table. Let's provide a default placeholder if needed,
                // or require it in the function signature. Wait, the prompt says "User provides password and optional email".
                // Nothing about 'name'. In Laravel, `name` is usually required. I'll pass a placeholder or let DB default handle it.
                'name' => $name ?? 'New User', // Placeholder to satisfy DB if required. Will be completed in Stage 2.
            ]);
            $user->save();

            // Consume the session
            $session->update([
                'status' => RegistrationSessionStatus::CONSUMED,
                'consumed_at' => now(),
                'last_activity_at' => now(),
            ]);

            // Claim any pre-user identity documents securely to this new user.
            // If this fails internally, it logs a critical error but does not fail the transaction.
            $this->claimService->claimDocumentsForUser($session, $user);

            return $user;
        });
    }
}
