<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\DTOs\DeliveryResult;
use App\Domain\Communication\DTOs\SmsMessage;
use App\Domain\Communication\Enums\DestinationType;
use App\Domain\Communication\Enums\OtpChannel;
use App\Domain\Communication\Enums\OtpPurpose;
use App\Domain\Communication\Enums\OtpStatus;
use App\Domain\Communication\Exceptions\OtpException;
use App\Domain\Communication\Exceptions\OtpRateLimitException;
use App\Domain\Communication\Models\OtpRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OTP Service — core lifecycle management.
 *
 * SECURITY INVARIANTS:
 *  1. Plaintext OTP codes are NEVER stored in the database.
 *  2. Plaintext OTP codes are NEVER written to application logs.
 *  3. Verification uses hash_equals comparison only.
 *  4. An OTP can be verified/consumed exactly once.
 *  5. Brute-force: max_attempts exceeded → OTP locked.
 *  6. Purpose mismatch on verify fails immediately.
 *  7. Tenant isolation: tenant-scoped OTPs locked to their tenant_id.
 */
class OtpService
{
    public function __construct(
        private readonly DestinationNormalizer $normalizer,
        private readonly CommunicationManager $communicationManager,
    ) {}

    /**
     * Request and send an OTP.
     *
     * @throws OtpRateLimitException
     * @throws OtpException
     */
    public function send(
        string $rawDestination,
        OtpPurpose $purpose,
        OtpChannel $channel = OtpChannel::SMS,
        ?string $tenantId = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        string $correlationId = '',
    ): OtpRecord {
        $destinationType = $channel === OtpChannel::SMS
            ? DestinationType::MOBILE
            : DestinationType::EMAIL;

        $canonical = $this->normalizer->normalize($rawDestination, $destinationType);
        $destHash = hash('sha256', $canonical);

        // Rate limit: per destination+purpose
        $this->enforceDestinationRateLimit($destHash, $purpose, $tenantId);

        // Rate limit: per IP
        if ($ipAddress) {
            $this->enforceIpRateLimit($ipAddress, $purpose);
        }

        // Cancel any active OTP for the same security context
        $this->cancelActivePrevious($destHash, $purpose, $tenantId);

        // Check resend cooldown from last active record
        $this->enforceResendCooldown($destHash, $purpose, $tenantId);

        // Generate cryptographically secure OTP
        $plaintextCode = $this->generateCode();

        // Hash for storage — never store plaintext
        $codeHash = Hash::make($plaintextCode);

        $ttl = config('otp.ttl', 300);
        $maxAttempts = (int) config('otp.max_attempts', 5);
        $provider = $channel === OtpChannel::SMS
            ? config('otp.default_sms_provider', 'log')
            : config('otp.default_email_provider', 'smtp');

        // Persist OTP record
        $record = OtpRecord::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'destination_type' => $destinationType,
            'destination_canonical' => $canonical,
            'destination_hash' => $destHash,
            'purpose' => $purpose,
            'channel' => $channel,
            'provider' => $provider,
            'code_hash' => $codeHash,
            'status' => OtpStatus::PENDING,
            'attempt_count' => 0,
            'send_count' => 0,
            'max_attempts' => $maxAttempts,
            'expires_at' => now()->addSeconds($ttl),
            'last_sent_at' => now(),
            'metadata' => [
                'ip_address' => $ipAddress,
                'correlation_id' => $correlationId,
            ],
        ]);

        // Dispatch to provider
        $result = $this->dispatchToProvider($record, $plaintextCode, $correlationId);

        // Zero out plaintext reference immediately
        $plaintextCode = '';

        if (! $result->accepted) {
            $record->update(['status' => OtpStatus::FAILED]);

            Log::warning('OTP delivery failed', [
                'otp_id' => $record->id,
                'provider' => $result->provider,
                'error_code' => $result->errorCode,
                'destination' => $this->normalizer->maskMobile($canonical),
                'purpose' => $purpose->value,
                'correlation' => $correlationId,
            ]);

            throw OtpException::deliveryFailed();
        }

        $record->update([
            'status' => OtpStatus::SENT,
            'send_count' => $record->send_count + 1,
        ]);

        // Increment destination rate limiter hit count after successful send
        RateLimiter::hit(
            $this->destinationLimiterKey($destHash, $purpose, $tenantId),
            config('otp.rate_limits.send_per_destination.window', 600)
        );

        if ($ipAddress) {
            RateLimiter::hit(
                $this->ipLimiterKey($ipAddress, $purpose),
                config('otp.rate_limits.send_per_ip.window', 3600)
            );
        }

        Log::info('OTP sent', [
            'otp_id' => $record->id,
            'destination' => $channel === OtpChannel::SMS
                ? $this->normalizer->maskMobile($canonical)
                : $this->normalizer->maskEmail($canonical),
            'purpose' => $purpose->value,
            'channel' => $channel->value,
            'correlation' => $correlationId,
        ]);

        return $record->fresh();
    }

    /**
     * Verify an OTP code against a record ID and purpose.
     * Uses DB lock to prevent concurrent consumption.
     *
     * @throws OtpException
     */
    public function verify(
        string $otpId,
        string $plaintextCode,
        OtpPurpose $purpose,
        ?string $tenantId = null,
    ): OtpRecord {
        $toThrow = null;
        $record = null;

        DB::transaction(function () use ($otpId, $plaintextCode, $purpose, $tenantId, &$record, &$toThrow) {
            /** @var OtpRecord|null $record */
            $record = OtpRecord::lockForUpdate()->find($otpId);

            if (! $record) {
                $toThrow = OtpException::notFound();

                return;
            }

            // Purpose guard
            if ($record->purpose !== $purpose) {
                $toThrow = OtpException::purposeMismatch();

                return;
            }

            // Tenant isolation guard
            if ($tenantId !== null && $record->tenant_id !== $tenantId) {
                // Deliberately ambiguous — treat as not found to prevent enumeration
                $toThrow = OtpException::notFound();

                return;
            }

            // Already consumed / verified
            if ($record->isConsumed()) {
                $toThrow = OtpException::alreadyUsed();

                return;
            }

            // Locked
            if ($record->isLocked()) {
                $toThrow = OtpException::locked();

                return;
            }

            // Expired
            if ($record->isExpired()) {
                $record->update(['status' => OtpStatus::EXPIRED]);
                $toThrow = OtpException::expired();

                return;
            }

            // Verify code — never log $plaintextCode
            if (! Hash::check($plaintextCode, $record->code_hash)) {
                $newCount = $record->attempt_count + 1;
                $locked = $newCount >= $record->max_attempts;

                $record->update([
                    'attempt_count' => $newCount,
                    'status' => $locked ? OtpStatus::LOCKED : $record->status,
                ]);

                $toThrow = $locked ? OtpException::locked() : OtpException::invalid();

                return;
            }

            // Success — mark verified and consumed atomically
            $now = now();
            $record->update([
                'status' => OtpStatus::CONSUMED,
                'verified_at' => $now,
                'consumed_at' => $now,
            ]);
        });

        if ($toThrow !== null) {
            throw $toThrow;
        }

        return $record->fresh();
    }

    /**
     * Resend an OTP: only resend within the same unexpired record if within cooldown policy.
     * Delegates to send() for a new OTP after cancelling the previous.
     */
    public function resend(
        string $rawDestination,
        OtpPurpose $purpose,
        OtpChannel $channel = OtpChannel::SMS,
        ?string $tenantId = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        string $correlationId = '',
    ): OtpRecord {
        // Delegate to send() — which enforces cooldown and cancels previous
        return $this->send(
            rawDestination: $rawDestination,
            purpose: $purpose,
            channel: $channel,
            tenantId: $tenantId,
            userId: $userId,
            ipAddress: $ipAddress,
            correlationId: $correlationId,
        );
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.length', 6);

        // Cryptographically secure random numeric OTP
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= (string) random_int(0, 9);
            }
        } while (strlen($code) !== $length);

        return $code;
    }

    private function dispatchToProvider(OtpRecord $record, string $plaintextCode, string $correlationId): DeliveryResult
    {
        $body = $this->composeMessage($plaintextCode, $record->purpose);

        if ($record->channel === OtpChannel::SMS) {
            $provider = $this->communicationManager->smsProvider($record->provider);
            $message = new SmsMessage(
                destination: $record->destination_canonical,
                body: $body,
                purpose: $record->purpose,
                correlationId: $correlationId,
                tenantId: $record->tenant_id,
            );

            return $provider->send($message);
        }

        // EMAIL channel
        $provider = $this->communicationManager->emailProvider($record->provider);

        return $provider->send(
            to: $record->destination_canonical,
            subject: 'Your verification code',
            body: $body,
        );
    }

    private function composeMessage(string $code, OtpPurpose $purpose): string
    {
        // Minimal bilingual-ready template. Full template system deferred to Wave 14.
        return "Your RAISA ERP verification code is: {$code}. Valid for ".
            ((int) config('otp.ttl', 300) / 60).' minutes. Do not share this code.';
    }

    private function cancelActivePrevious(string $destHash, OtpPurpose $purpose, ?string $tenantId): void
    {
        OtpRecord::where('destination_hash', $destHash)
            ->where('purpose', $purpose)
            ->where('tenant_id', $tenantId)
            ->where('status', OtpStatus::SENT)
            ->update(['status' => OtpStatus::CANCELLED]);
    }

    private function enforceResendCooldown(string $destHash, OtpPurpose $purpose, ?string $tenantId): void
    {
        $cooldown = (int) config('otp.resend_cooldown', 60);

        $lastSent = OtpRecord::where('destination_hash', $destHash)
            ->where('purpose', $purpose)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('last_sent_at')
            ->value('last_sent_at');

        if ($lastSent === null) {
            return;
        }

        $secondsSinceLast = now()->diffInSeconds($lastSent, false);

        // diffInSeconds returns negative when $lastSent is in the past
        $elapsed = abs($secondsSinceLast);

        if ($elapsed < $cooldown) {
            $retryAfter = $cooldown - (int) $elapsed;
            throw OtpException::resendTooSoon($retryAfter);
        }
    }

    private function enforceDestinationRateLimit(string $destHash, OtpPurpose $purpose, ?string $tenantId): void
    {
        $key = $this->destinationLimiterKey($destHash, $purpose, $tenantId);
        $max = (int) config('otp.rate_limits.send_per_destination.max', 3);
        $window = (int) config('otp.rate_limits.send_per_destination.window', 600);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retryAfter = RateLimiter::availableIn($key);
            throw new OtpRateLimitException(
                "OTP send rate limit exceeded for destination. Retry after {$retryAfter}s.",
                $retryAfter
            );
        }
    }

    private function enforceIpRateLimit(string $ipAddress, OtpPurpose $purpose): void
    {
        $key = $this->ipLimiterKey($ipAddress, $purpose);
        $max = (int) config('otp.rate_limits.send_per_ip.max', 10);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retryAfter = RateLimiter::availableIn($key);
            throw new OtpRateLimitException(
                "OTP send rate limit exceeded for IP. Retry after {$retryAfter}s.",
                $retryAfter
            );
        }
    }

    private function destinationLimiterKey(string $destHash, OtpPurpose $purpose, ?string $tenantId): string
    {
        return 'otp:send:dest:'.$destHash.':'.$purpose->value.':'.($tenantId ?? 'platform');
    }

    private function ipLimiterKey(string $ipAddress, OtpPurpose $purpose): string
    {
        return 'otp:send:ip:'.hash('sha256', $ipAddress).':'.$purpose->value;
    }
}
