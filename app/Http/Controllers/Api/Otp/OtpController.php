<?php

namespace App\Http\Controllers\Api\Otp;

use App\Domain\Communication\Enums\OtpChannel;
use App\Domain\Communication\Enums\OtpPurpose;
use App\Domain\Communication\Exceptions\InvalidDestinationException;
use App\Domain\Communication\Exceptions\OtpException;
use App\Domain\Communication\Exceptions\OtpRateLimitException;
use App\Domain\Communication\Services\OtpService;
use App\Http\Requests\Otp\OtpSendRequest;
use App\Http\Requests\Otp\OtpVerifyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class OtpController
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {}

    /**
     * POST /api/otp/send
     *
     * Public for explicitly public purposes (registration, password reset).
     * Anti-enumeration: always returns 202 even if destination has no account.
     */
    public function send(OtpSendRequest $request): JsonResponse
    {
        $purpose = OtpPurpose::from($request->validated('purpose'));
        $channel = OtpChannel::from($request->validated('channel'));

        try {
            $record = $this->otpService->send(
                rawDestination: $request->validated('destination'),
                purpose: $purpose,
                channel: $channel,
                tenantId: null, // Platform-level for public flows; tenant-scoped flows will pass via service layer
                userId: $request->user()?->id,
                ipAddress: $request->ip(),
                correlationId: $request->header('X-Correlation-Id', Str::uuid()->toString()),
            );

            return response()->json([
                'otp_id' => $record->id,
                'expires_in' => config('otp.ttl', 300),
                'message' => 'Verification code sent.',
            ], 202);
        } catch (OtpRateLimitException $e) {
            return response()->json([
                'error' => 'OTP_RATE_LIMITED',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $e->retryAfter,
            ], 429);
        } catch (OtpException $e) {
            if ($e->getCode() === 429) {
                return response()->json([
                    'error' => 'OTP_RESEND_TOO_SOON',
                    'message' => 'Please wait before requesting another code.',
                    'retry_after' => $e->retryAfter,
                ], 429);
            }

            // Anti-enumeration: delivery failure is opaque to client
            return response()->json([
                'error' => 'OTP_DELIVERY_FAILED',
                'message' => 'Unable to send verification code. Please try again.',
            ], 502);
        } catch (InvalidDestinationException) {
            return response()->json([
                'error' => 'OTP_DESTINATION_INVALID',
                'message' => 'The provided destination is invalid.',
            ], 422);
        }
    }

    /**
     * POST /api/otp/verify
     */
    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        $purpose = OtpPurpose::from($request->validated('purpose'));

        try {
            $record = $this->otpService->verify(
                otpId: $request->validated('otp_id'),
                plaintextCode: $request->validated('code'),
                purpose: $purpose,
                tenantId: null, // Platform-level for public flows
            );

            return response()->json([
                'verified' => true,
                'otp_id' => $record->id,
                'verified_at' => $record->verified_at?->toIso8601String(),
            ], 200);
        } catch (OtpException $e) {
            $errorCode = match ($e->getMessage()) {
                'OTP has expired.' => 'OTP_EXPIRED',
                'OTP is locked due to too many failed attempts.' => 'OTP_LOCKED',
                'OTP has already been used.' => 'OTP_ALREADY_USED',
                'OTP purpose does not match.' => 'OTP_PURPOSE_MISMATCH',
                default => 'OTP_INVALID',
            };

            return response()->json([
                'error' => $errorCode,
                'message' => 'Verification failed.',
            ], 422);
        }
    }

    /**
     * POST /api/otp/resend
     */
    public function resend(OtpSendRequest $request): JsonResponse
    {
        // Resend delegates to send — which enforces cooldown
        return $this->send($request);
    }
}
