<?php

namespace Tests\Feature\Identity;

use App\Domain\Identity\Enums\IdentityVerificationProviderStatus;
use App\Domain\Identity\Enums\IdentityVerificationStatus;
use App\Domain\Identity\Services\IdentityVerificationService;
use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use App\Models\User;
use App\Models\UserIdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_verifier_never_returns_verified()
    {
        $user = User::factory()->create();

        $cipher = app(SensitiveDataCipherInterface::class);
        $encryptedNid = $cipher->encrypt('1234567890');

        UserIdentityVerification::create([
            'user_id' => $user->id,
            'document_type' => 'nid',
            'status' => IdentityVerificationStatus::EXTRACTED,
            'provider' => 'null',
            'normalized_name' => 'John Doe',
            'normalized_dob' => '1990-01-01',
            'nid_number_encrypted' => $encryptedNid,
        ]);

        $service = app(IdentityVerificationService::class);

        $result = $service->verifyIdentity($user);

        $this->assertEquals(IdentityVerificationProviderStatus::MANUAL_REVIEW_REQUIRED, $result->status);

        $verification = UserIdentityVerification::where('user_id', $user->id)->first();
        $this->assertEquals(IdentityVerificationStatus::MANUAL_REVIEW_REQUIRED, $verification->status);
        $this->assertTrue($verification->manual_review_required);
    }
}
