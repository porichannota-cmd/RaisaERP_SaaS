<?php

declare(strict_types=1);

namespace App\Domain\Profile\Services;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Support\Facades\DB;

class UserConsentService
{
    public function __construct(private readonly ProfileCompletionService $completionService) {}

    public function grantConsent(User $user, array $data): UserConsent
    {
        return DB::transaction(function () use ($user, $data) {
            $consent = UserConsent::updateOrCreate(
                ['user_id' => $user->id, 'consent_type' => $data['consent_type']],
                [
                    'document_version' => $data['document_version'],
                    'document_hash' => $data['document_hash'] ?? null,
                    'accepted_at' => now(),
                    'revoked_at' => null,
                    'source' => $data['source'] ?? 'web',
                    'ip_fingerprint' => $data['ip_fingerprint'] ?? request()->ip(),
                ]
            );

            $this->completionService->recalculate($user);

            return $consent;
        });
    }

    public function revokeConsent(User $user, string $consentType): void
    {
        DB::transaction(function () use ($user, $consentType) {
            UserConsent::where('user_id', $user->id)
                ->where('consent_type', $consentType)
                ->update(['revoked_at' => now()]);

            $this->completionService->recalculate($user);
        });
    }
}
