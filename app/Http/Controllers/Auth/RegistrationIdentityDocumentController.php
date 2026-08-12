<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Registration\Enums\RegistrationDocumentKind;
use App\Domain\Registration\Services\RegistrationIdentityDocumentService;
use App\Domain\Registration\Services\RegistrationSessionTokenService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Registration\StoreRegistrationIdentityDocumentRequest;
use App\Models\RegistrationIdentityDocument;
use App\Models\RegistrationSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RegistrationIdentityDocumentController extends Controller
{
    public function __construct(
        private readonly RegistrationIdentityDocumentService $documentService,
        private readonly RegistrationSessionTokenService $tokenService
    ) {}

    public function store(StoreRegistrationIdentityDocumentRequest $request): JsonResponse
    {
        $session = $this->resolveAndAuthorizeSession($request);

        $kind = RegistrationDocumentKind::from($request->validated('kind'));
        $file = $request->file('file');

        $document = $this->documentService->upload($session, $file, $kind);

        return response()->json([
            'id' => $document->id,
            'kind' => $document->kind->value,
            'status' => $document->status->value,
            'expires_at' => $document->expires_at?->toIso8601String(),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $session = $this->resolveAndAuthorizeSession($request);

        $documents = RegistrationIdentityDocument::where('registration_session_id', $session->id)
            ->get()
            ->map(fn (RegistrationIdentityDocument $doc) => [
                'id' => $doc->id,
                'kind' => $doc->kind->value,
                'status' => $doc->status->value,
                'expires_at' => $doc->expires_at?->toIso8601String(),
            ]);

        return response()->json(['documents' => $documents]);
    }

    public function destroy(Request $request, string $documentId): JsonResponse
    {
        $session = $this->resolveAndAuthorizeSession($request);

        $this->documentService->delete($session, $documentId);

        return response()->json([], 204);
    }

    /**
     * Resolves the session via public reference and verifies the raw token.
     */
    private function resolveAndAuthorizeSession(Request $request): RegistrationSession
    {
        // Support token in header X-Registration-Token or in body
        $reference = $request->header('X-Registration-Reference') ?? $request->input('reference');
        $rawToken = $request->header('X-Registration-Token') ?? $request->input('token');

        if (! $reference || ! $rawToken) {
            throw ValidationException::withMessages([
                'auth' => ['REGISTRATION_AUTH_MISSING', 'Registration reference and token are required.'],
            ]);
        }

        $session = RegistrationSession::where('public_reference', $reference)->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'auth' => ['REGISTRATION_AUTH_FAILED', 'Invalid registration session.'],
            ]);
        }

        if (! $this->tokenService->verify($rawToken, $session->token_hash)) {
            throw ValidationException::withMessages([
                'auth' => ['REGISTRATION_AUTH_FAILED', 'Invalid registration token.'],
            ]);
        }

        return $session;
    }
}
