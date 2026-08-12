<?php

namespace Tests\Feature\Communication;

use App\Domain\Communication\Enums\DestinationType;
use App\Domain\Communication\Enums\OtpChannel;
use App\Domain\Communication\Enums\OtpPurpose;
use App\Domain\Communication\Enums\OtpStatus;
use App\Domain\Communication\Exceptions\OtpException;
use App\Domain\Communication\Exceptions\OtpRateLimitException;
use App\Domain\Communication\Models\OtpRecord;
use App\Domain\Communication\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * OTP security test matrix.
 *
 * Tests are ordered to cover the full brute-force, replay,
 * purpose-mismatch, and tenant-isolation attack surfaces.
 */
class OtpSecurityTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otpService = app(OtpService::class);
        RateLimiter::clear('otp:send:dest:'.hash('sha256', '+8801712345678').':registration.mobile:platform');
        RateLimiter::clear('otp:send:dest:'.hash('sha256', '+8801712345679').':registration.mobile:platform');
    }

    // -------------------------------------------------------------------------
    // Correct OTP → success
    // -------------------------------------------------------------------------

    public function test_correct_otp_verifies_successfully(): void
    {
        [$record, $code] = $this->createOtpWithCode('+8801712345678', OtpPurpose::REGISTRATION_MOBILE);

        $verified = $this->otpService->verify(
            otpId: $record->id,
            plaintextCode: $code,
            purpose: OtpPurpose::REGISTRATION_MOBILE,
        );

        $this->assertSame(OtpStatus::CONSUMED, $verified->status);
        $this->assertNotNull($verified->verified_at);
        $this->assertNotNull($verified->consumed_at);
    }

    // -------------------------------------------------------------------------
    // Wrong OTP → failure (not locked)
    // -------------------------------------------------------------------------

    public function test_wrong_otp_fails(): void
    {
        [$record] = $this->createOtpWithCode('+8801712345678', OtpPurpose::REGISTRATION_MOBILE);

        $this->expectException(OtpException::class);

        $this->otpService->verify(
            otpId: $record->id,
            plaintextCode: '000000',
            purpose: OtpPurpose::REGISTRATION_MOBILE,
        );
    }

    // -------------------------------------------------------------------------
    // Wrong OTP 5× → locked
    // -------------------------------------------------------------------------

    public function test_wrong_otp_five_times_locks_record(): void
    {
        [$record] = $this->createOtpWithCode('+8801712345678', OtpPurpose::REGISTRATION_MOBILE);

        for ($i = 0; $i < 4; $i++) {
            try {
                $this->otpService->verify($record->id, '000000', OtpPurpose::REGISTRATION_MOBILE);
            } catch (OtpException) {
            }
        }

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('OTP is locked due to too many failed attempts.');

        $this->otpService->verify($record->id, '000000', OtpPurpose::REGISTRATION_MOBILE);
    }

    public function test_after_lock_correct_code_also_fails(): void
    {
        [$record, $code] = $this->createOtpWithCode('+8801712345678', OtpPurpose::REGISTRATION_MOBILE);

        // Lock it
        for ($i = 0; $i <= 5; $i++) {
            try {
                $this->otpService->verify($record->id, '000000', OtpPurpose::REGISTRATION_MOBILE);
            } catch (OtpException) {
            }
        }

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('OTP is locked');

        $this->otpService->verify($record->id, $code, OtpPurpose::REGISTRATION_MOBILE);
    }

    // -------------------------------------------------------------------------
    // Expired OTP → failure
    // -------------------------------------------------------------------------

    public function test_expired_otp_fails(): void
    {
        [$record] = $this->createOtpWithCode('+8801712345678', OtpPurpose::REGISTRATION_MOBILE, expiredAt: now()->subMinute());

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('OTP has expired.');

        $this->otpService->verify($record->id, '123456', OtpPurpose::REGISTRATION_MOBILE);
    }

    // -------------------------------------------------------------------------
    // Consumed OTP → replay failure
    // -------------------------------------------------------------------------

    public function test_consumed_otp_cannot_be_used_again(): void
    {
        [$record, $code] = $this->createOtpWithCode('+8801712345678', OtpPurpose::REGISTRATION_MOBILE);

        // First verify succeeds
        $this->otpService->verify($record->id, $code, OtpPurpose::REGISTRATION_MOBILE);

        // Replay fails
        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('OTP has already been used.');

        $this->otpService->verify($record->id, $code, OtpPurpose::REGISTRATION_MOBILE);
    }

    // -------------------------------------------------------------------------
    // Purpose mismatch → failure
    // -------------------------------------------------------------------------

    public function test_purpose_mismatch_fails(): void
    {
        [$record, $code] = $this->createOtpWithCode('+8801712345678', OtpPurpose::REGISTRATION_MOBILE);

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('OTP purpose does not match.');

        // Try to verify under a different purpose
        $this->otpService->verify($record->id, $code, OtpPurpose::PASSWORD_RESET);
    }

    // -------------------------------------------------------------------------
    // Tenant isolation
    // -------------------------------------------------------------------------

    public function test_otp_not_valid_for_different_tenant(): void
    {
        [$record, $code] = $this->createOtpWithCode(
            '+8801712345678',
            OtpPurpose::LOGIN,
            tenantId: 'tenant-A'
        );

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('OTP record not found.'); // Anti-enumeration: returns not found

        $this->otpService->verify(
            otpId: $record->id,
            plaintextCode: $code,
            purpose: OtpPurpose::LOGIN,
            tenantId: 'tenant-B',
        );
    }

    // -------------------------------------------------------------------------
    // Rate limiting — destination
    // -------------------------------------------------------------------------

    public function test_destination_rate_limit_blocks_excess_sends(): void
    {
        $max = config('otp.rate_limits.send_per_destination.max', 3);

        // Fill up the rate limiter manually
        $destHash = hash('sha256', '+8801712345679');
        $key = 'otp:send:dest:'.$destHash.':'.OtpPurpose::REGISTRATION_MOBILE->value.':platform';

        for ($i = 0; $i < $max; $i++) {
            RateLimiter::hit($key, 600);
        }

        $this->expectException(OtpRateLimitException::class);

        $this->otpService->send(
            rawDestination: '+8801712345679',
            purpose: OtpPurpose::REGISTRATION_MOBILE,
            channel: OtpChannel::SMS,
        );
    }

    // -------------------------------------------------------------------------
    // OTP code is never stored in plaintext
    // -------------------------------------------------------------------------

    public function test_otp_code_hash_is_stored_not_plaintext(): void
    {
        [$record, $code] = $this->createOtpWithCode('+8801712345678', OtpPurpose::REGISTRATION_MOBILE);

        // Retrieve raw code_hash from DB — should NOT equal the plaintext code
        $rawRecord = DB::table('otp_records')->where('id', $record->id)->first();

        $this->assertNotSame($code, $rawRecord->code_hash);
        $this->assertTrue(Hash::check($code, $rawRecord->code_hash));
    }

    // -------------------------------------------------------------------------
    // code_hash is hidden from model serialization
    // -------------------------------------------------------------------------

    public function test_otp_record_code_hash_is_hidden_from_serialization(): void
    {
        [$record] = $this->createOtpWithCode('+8801712345678', OtpPurpose::REGISTRATION_MOBILE);

        $array = $record->toArray();
        $this->assertArrayNotHasKey('code_hash', $array);
    }

    // -------------------------------------------------------------------------
    // Resend cooldown
    // -------------------------------------------------------------------------

    public function test_resend_too_soon_throws(): void
    {
        // Create a record with last_sent_at = now (within cooldown)
        $canonical = '+8801712345678';
        $destHash = hash('sha256', $canonical);

        OtpRecord::create([
            'destination_type' => DestinationType::MOBILE,
            'destination_canonical' => $canonical,
            'destination_hash' => $destHash,
            'purpose' => OtpPurpose::REGISTRATION_MOBILE,
            'channel' => OtpChannel::SMS,
            'provider' => 'log',
            'code_hash' => Hash::make('123456'),
            'status' => OtpStatus::SENT,
            'attempt_count' => 0,
            'send_count' => 1,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now()->subSeconds(10), // too recent
        ]);

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage('Please wait');

        $this->otpService->send(
            rawDestination: '01712345678',
            purpose: OtpPurpose::REGISTRATION_MOBILE,
            channel: OtpChannel::SMS,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Create an OtpRecord and return both the record and the plaintext code.
     * Uses Hash::make to store — replicates OtpService internals for test isolation.
     *
     * @return array{OtpRecord, string}
     */
    private function createOtpWithCode(
        string $canonical,
        OtpPurpose $purpose,
        ?string $tenantId = null,
        ?Carbon $expiredAt = null,
    ): array {
        $code = '123456';
        $destHash = hash('sha256', $canonical);

        $record = OtpRecord::create([
            'tenant_id' => $tenantId,
            'destination_type' => DestinationType::MOBILE,
            'destination_canonical' => $canonical,
            'destination_hash' => $destHash,
            'purpose' => $purpose,
            'channel' => OtpChannel::SMS,
            'provider' => 'log',
            'code_hash' => Hash::make($code),
            'status' => OtpStatus::SENT,
            'attempt_count' => 0,
            'send_count' => 1,
            'max_attempts' => 5,
            'expires_at' => $expiredAt ?? now()->addMinutes(5),
            'last_sent_at' => now()->subMinutes(5), // Past cooldown
        ]);

        return [$record, $code];
    }
}
