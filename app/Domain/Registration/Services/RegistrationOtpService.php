<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Communication\Enums\OtpPurpose;
use App\Domain\Communication\Services\OtpService;
use App\Domain\Registration\Enums\RegistrationSessionStatus;
use App\Models\RegistrationSession;
use Illuminate\Validation\ValidationException;

class RegistrationOtpService
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly RegistrationSessionTokenService $tokenService
    ) {}

    /**
     * Sends the OTP and transitions session to OTP_PENDING.
     */
    public function sendOtp(RegistrationSession $session, string $token): void
    {
        $this->verifySessionToken($session, $token);
        
        if ($session->isExpired() || $session->isConsumed()) {
            throw ValidationException::withMessages(['session' => 'Session is expired or consumed.']);
        }

        $otpRecord = $this->otpService->send(
            rawDestination: $session->mobile_canonical,
            purpose: OtpPurpose::REGISTRATION_MOBILE,
            ipAddress: request()->ip()
        );

        $session->update([
            'status' => RegistrationSessionStatus::OTP_PENDING,
            'otp_record_id' => $otpRecord->id,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Verifies the OTP and transitions session to OTP_VERIFIED.
     */
    public function verifyOtp(RegistrationSession $session, string $token, string $code): void
    {
        $this->verifySessionToken($session, $token);

        if ($session->isExpired() || $session->isConsumed()) {
            throw ValidationException::withMessages(['session' => 'Session is expired or consumed.']);
        }

        if ($session->status !== RegistrationSessionStatus::OTP_PENDING && $session->status !== RegistrationSessionStatus::INITIATED) {
            // Already verified or in invalid state
            throw ValidationException::withMessages(['session' => 'Session is not in a valid state for OTP verification.']);
        }

        // Delegate to OtpService which handles attempts, locking, and consumption
        // If it throws, the exception will bubble up (OtpException or ValidationException).
        $this->otpService->verify(
            rawDestination: $session->mobile_canonical,
            purpose: OtpPurpose::REGISTRATION_MOBILE,
            plaintextCode: $code
        );

        // Verification successful, elevate session state
        $session->update([
            'status' => RegistrationSessionStatus::OTP_VERIFIED,
            'otp_verified_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    private function verifySessionToken(RegistrationSession $session, string $token): void
    {
        if (! $this->tokenService->verify($token, $session->token_hash)) {
            throw ValidationException::withMessages(['token' => 'Invalid registration token.']);
        }
    }
}
