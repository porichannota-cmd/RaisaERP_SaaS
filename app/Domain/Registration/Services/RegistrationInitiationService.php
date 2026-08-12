<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Communication\Services\DestinationNormalizer;
use App\Domain\Registration\Enums\RegistrationSessionStatus;
use App\Domain\Registration\Enums\RegistrationSource;
use App\Models\RegistrationSession;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;

class RegistrationInitiationService
{
    public function __construct(
        private readonly DestinationNormalizer $normalizer,
        private readonly RegistrationSessionTokenService $tokenService
    ) {}

    /**
     * Initiates a registration session for a mobile number.
     * 
     * @return array{session: RegistrationSession, token: string}
     * @throws InvalidArgumentException
     */
    public function initiate(string $mobile, RegistrationSource $source = RegistrationSource::PUBLIC): array
    {
        // 1. Normalize Mobile
        $canonicalMobile = $this->normalizer->normalize($mobile, \App\Domain\Communication\Enums\DestinationType::MOBILE);

        // Note: We don't throw an error here if the mobile is already registered, 
        // because we don't want to enumerate users before OTP verification,
        // or we CAN fail fast. The prompt says: "Application precheck improves UX only. 
        // Concurrent requests must still be safe." 
        // For Stage 1 initiation, we just create the session.
        
        $tokenData = $this->tokenService->generate();
        $token = $tokenData['rawToken'];
        $tokenHash = $tokenData['storedHash'];
        
        // 3. Create Session
        $session = RegistrationSession::create([
            'id' => (string) Ulid::generate(),
            'public_reference' => (string) Str::uuid(),
            'token_hash' => $tokenHash,
            'mobile_canonical' => $canonicalMobile,
            'registration_source' => $source,
            'status' => RegistrationSessionStatus::INITIATED,
            'expires_at' => now()->addMinutes((int) config('registration.session_ttl_minutes', 30)),
            'last_activity_at' => now(),
            'ip_hash' => request()->ip() ? hash('sha256', request()->ip()) : null,
        ]);

        return [
            'session' => $session,
            'token' => $token,
        ];
    }
}
