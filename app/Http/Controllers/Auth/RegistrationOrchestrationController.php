<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Registration\Enums\RegistrationSource;
use App\Domain\Registration\Services\RegistrationAccountService;
use App\Domain\Registration\Services\RegistrationInitiationService;
use App\Domain\Registration\Services\RegistrationOtpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegistrationAccountRequest;
use App\Http\Requests\Registration\RegistrationInitiateRequest;
use App\Http\Requests\Registration\RegistrationOtpSendRequest;
use App\Http\Requests\Registration\RegistrationOtpVerifyRequest;
use App\Models\RegistrationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RegistrationOrchestrationController extends Controller
{
    public function __construct(
        private readonly RegistrationInitiationService $initiationService,
        private readonly RegistrationOtpService $otpService,
        private readonly RegistrationAccountService $accountService
    ) {}

    public function initiate(RegistrationInitiateRequest $request): JsonResponse
    {
        $result = $this->initiationService->initiate(
            mobile: $request->validated('mobile'),
            source: RegistrationSource::PUBLIC
        );

        return response()->json([
            'reference' => $result['session']->public_reference,
            'token' => $result['token'],
            'status' => $result['session']->status->value,
            'expires_at' => $result['session']->expires_at->toIso8601String(),
        ], 201);
    }

    public function sendOtp(RegistrationOtpSendRequest $request): JsonResponse
    {
        $session = $this->getSessionOrFail($request->validated('reference'));

        $this->otpService->sendOtp($session, $request->validated('token'));

        return response()->json([
            'status' => $session->status->value,
        ]);
    }

    public function verifyOtp(RegistrationOtpVerifyRequest $request): JsonResponse
    {
        $session = $this->getSessionOrFail($request->validated('reference'));

        $this->otpService->verifyOtp(
            session: $session,
            token: $request->validated('token'),
            code: $request->validated('code')
        );

        return response()->json([
            'status' => $session->status->value,
        ]);
    }

    public function createAccount(RegistrationAccountRequest $request): JsonResponse
    {
        $user = $this->accountService->createAccount(
            publicReference: $request->validated('reference'),
            token: $request->validated('token'),
            password: $request->validated('password'),
            email: $request->validated('email'),
            name: $request->validated('name')
        );

        // Optionally log the user in immediately after creation, 
        // per Wave 2B requirements: "Authentication/login is allowed under limited account-state policy."
        // We can let the frontend redirect to login, or authenticate here.
        // We will just return success and let the client decide, or auth them.
        Auth::login($user);
        request()->session()->regenerate();

        return response()->json([
            'enterprise_user_id' => $user->enterprise_user_id,
            'account_status' => $user->account_status->value,
            'redirect' => route('dashboard', absolute: false),
        ], 201);
    }

    private function getSessionOrFail(string $reference): RegistrationSession
    {
        $session = RegistrationSession::where('public_reference', $reference)->first();

        if (! $session) {
            throw ValidationException::withMessages(['reference' => 'Invalid registration session.']);
        }

        return $session;
    }
}
