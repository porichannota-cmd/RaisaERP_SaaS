<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Communication\Services\DestinationNormalizer;

/**
 * Resolves an ambiguous login identifier (mobile or email) into canonical credentials.
 */
class LoginIdentifierResolver
{
    public function __construct(
        private readonly DestinationNormalizer $normalizer
    ) {}

    /**
     * Resolves the given identifier string into an Auth::attempt compatible array.
     * 
     * @param string $identifier The raw input from the user (email or mobile)
     * @param string $password The raw password
     * @return array<string, string>
     */
    public function resolveCredentials(string $identifier, string $password): array
    {
        $credentials = ['password' => $password];

        // If it looks like a valid email, use email. Otherwise, normalize as mobile.
        // We use a simple structural check. If the identifier contains '@', it's treated as an email.
        if (str_contains($identifier, '@')) {
            $credentials['email'] = strtolower(trim($identifier));
        } else {
            // Attempt to normalize as mobile
            try {
                $credentials['mobile_canonical'] = $this->normalizer->normalize($identifier, \App\Domain\Communication\Enums\DestinationType::MOBILE);
            } catch (\InvalidArgumentException $e) {
                // If it fails normalization, we fallback to a dummy email/mobile that will fail auth,
                // or we could throw an exception. Returning a safely failing credential is standard.
                $credentials['email'] = $identifier; 
            }
        }

        return $credentials;
    }

    /**
     * Resolves the identifier to a canonical string suitable for rate limiting.
     */
    public function resolveRateLimitKey(string $identifier): string
    {
        if (str_contains($identifier, '@')) {
            return strtolower(trim($identifier));
        }

        try {
            return $this->normalizer->normalize($identifier, 'BD');
        } catch (\InvalidArgumentException $e) {
            return strtolower(trim($identifier));
        }
    }
}
