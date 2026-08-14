<?php

namespace Tests\Feature\Identity;

use App\Domain\Identity\Enums\IdentityExtractionProviderStatus;
use App\Domain\Identity\Enums\IdentityVerificationStatus;
use App\Domain\Identity\Services\IdentityExtractionService;
use App\Domain\Registration\Enums\RegistrationSource;
use App\Models\RegistrationIdentityDocument;
use App\Models\RegistrationSession;
use App\Models\User;
use App\Models\UserIdentityVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_extractor_returns_not_available()
    {
        $user = User::factory()->create();
        $session = RegistrationSession::create(['id' => Str::uuid()->toString(), 'token_hash' => hash('sha256', Str::random(64)), 'mobile_canonical' => '+8801700000000', 'registration_source' => RegistrationSource::PUBLIC->value, 'status' => 'initiated', 'expires_at' => now()->addHour()]);
        $front = RegistrationIdentityDocument::create([
            'id' => Str::uuid()->toString(),
            'registration_session_id' => $session->id,
            'kind' => 'nid_front',
            'storage_disk' => 'local',
            'storage_path' => 'path/to/file',
            'original_filename_safe' => 'test.jpg',
            'detected_mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'test'),
            'status' => 'claimed',
            'claimed_by_user_id' => $user->id,
            'claimed_at' => now(),
        ]);

        $service = app(IdentityExtractionService::class);

        $result = $service->extractIdentityData($user, $front);

        $this->assertEquals(IdentityExtractionProviderStatus::NOT_AVAILABLE, $result->status);
        $this->assertEquals('NULL_PROVIDER_ACTIVE', $result->failureCode);

        $verification = UserIdentityVerification::where('user_id', $user->id)->first();
        $this->assertNotNull($verification);
        $this->assertEquals(IdentityVerificationStatus::FAILED, $verification->status);
    }

    public function test_extraction_fails_if_document_unowned()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $session = RegistrationSession::create(['id' => Str::uuid()->toString(), 'token_hash' => hash('sha256', Str::random(64)), 'mobile_canonical' => '+8801700000001', 'registration_source' => RegistrationSource::PUBLIC->value, 'status' => 'initiated', 'expires_at' => now()->addHour()]);
        $front = RegistrationIdentityDocument::create([
            'id' => Str::uuid()->toString(),
            'registration_session_id' => $session->id,
            'kind' => 'nid_front',
            'storage_disk' => 'local',
            'storage_path' => 'path/to/file',
            'original_filename_safe' => 'test.jpg',
            'detected_mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'checksum_sha256' => hash('sha256', 'test'),
            'status' => 'claimed',
            'claimed_by_user_id' => $userB->id,
            'claimed_at' => now(),
        ]);

        $service = app(IdentityExtractionService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Document does not belong to user.');

        $service->extractIdentityData($userA, $front);
    }
}
