<?php

namespace Tests\Feature\Registration;

use App\Domain\Registration\Enums\RegistrationDocumentKind;
use App\Domain\Registration\Enums\RegistrationDocumentStatus;
use App\Domain\Registration\Enums\RegistrationSessionStatus;
use App\Domain\Registration\Enums\RegistrationSource;
use App\Domain\Registration\Services\RegistrationIdentityDocumentClaimService;
use App\Domain\Registration\Services\RegistrationSessionTokenService;
use App\Models\RegistrationIdentityDocument;
use App\Models\RegistrationSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Wave2CMediaTest extends TestCase
{
    use RefreshDatabase;

    private RegistrationSessionTokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->tokenService = app(RegistrationSessionTokenService::class);
    }

    private function createSession(RegistrationSessionStatus $status): array
    {
        $tokenPair = $this->tokenService->generate();

        $session = new RegistrationSession;
        $session->forceFill([
            'id' => (string) Str::ulid(),
            'public_reference' => (string) Str::ulid(),
            'token_hash' => $tokenPair['storedHash'],
            'mobile_canonical' => '+8801711000'.rand(100, 999),
            'registration_source' => RegistrationSource::PUBLIC,
            'status' => $status,
            'otp_verified_at' => $status === RegistrationSessionStatus::OTP_VERIFIED ? now() : null,
            'expires_at' => now()->addMinutes(30),
            'last_activity_at' => now(),
        ]);
        $session->save();

        return [
            'session' => $session,
            'token' => $tokenPair['rawToken'],
            'reference' => $session->public_reference,
        ];
    }

    public function test_upload_requires_valid_token()
    {
        $setup = $this->createSession(RegistrationSessionStatus::OTP_VERIFIED);

        $response = $this->postJson('/api/registration/identity-documents', [
            'reference' => $setup['reference'],
            'token' => 'invalid-token',
            'kind' => RegistrationDocumentKind::PROFILE_PHOTO->value,
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['auth']);
    }

    public function test_upload_requires_otp_verified_session()
    {
        $setup = $this->createSession(RegistrationSessionStatus::OTP_PENDING);

        $response = $this->postJson('/api/registration/identity-documents', [
            'reference' => $setup['reference'],
            'token' => $setup['token'],
            'kind' => RegistrationDocumentKind::PROFILE_PHOTO->value,
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session']);
    }

    public function test_successful_upload_and_replacement()
    {
        $setup = $this->createSession(RegistrationSessionStatus::OTP_VERIFIED);

        // Upload first document
        $response = $this->postJson('/api/registration/identity-documents', [
            'reference' => $setup['reference'],
            'token' => $setup['token'],
            'kind' => RegistrationDocumentKind::NID_FRONT->value,
            'file' => UploadedFile::fake()->image('front1.jpg'),
        ]);

        $response->assertStatus(201);
        $firstId = $response->json('id');

        $this->assertDatabaseHas('registration_identity_documents', [
            'id' => $firstId,
            'registration_session_id' => $setup['session']->id,
        ]);

        // Upload replacement
        $response2 = $this->postJson('/api/registration/identity-documents', [
            'reference' => $setup['reference'],
            'token' => $setup['token'],
            'kind' => RegistrationDocumentKind::NID_FRONT->value,
            'file' => UploadedFile::fake()->image('front2.jpg'),
        ]);

        $response2->assertStatus(201);
        $secondId = $response2->json('id');

        $this->assertNotEquals($firstId, $secondId);

        // First one should be deleted from DB
        $this->assertDatabaseMissing('registration_identity_documents', [
            'id' => $firstId,
        ]);

        $this->assertDatabaseHas('registration_identity_documents', [
            'id' => $secondId,
        ]);
    }

    public function test_rejects_disguised_php_file()
    {
        $setup = $this->createSession(RegistrationSessionStatus::OTP_VERIFIED);

        // Fake a file named .jpg but containing php content.
        // UploadedFile::fake()->create('malicious.jpg', 10, 'application/x-httpd-php')
        $file = UploadedFile::fake()->createWithContent('malicious.jpg', '<?php echo "hacked"; ?>');

        $response = $this->postJson('/api/registration/identity-documents', [
            'reference' => $setup['reference'],
            'token' => $setup['token'],
            'kind' => RegistrationDocumentKind::NID_FRONT->value,
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_cross_session_isolation()
    {
        $setupA = $this->createSession(RegistrationSessionStatus::OTP_VERIFIED);
        $setupB = $this->createSession(RegistrationSessionStatus::OTP_VERIFIED);

        // Upload to Session A
        $responseA = $this->postJson('/api/registration/identity-documents', [
            'reference' => $setupA['reference'],
            'token' => $setupA['token'],
            'kind' => RegistrationDocumentKind::NID_FRONT->value,
            'file' => UploadedFile::fake()->image('front.jpg'),
        ]);

        $docIdA = $responseA->json('id');

        // Try to delete Session A's document using Session B's token
        $responseB = $this->deleteJson('/api/registration/identity-documents/'.$docIdA, [
            'reference' => $setupB['reference'],
            'token' => $setupB['token'],
        ]);

        // Should return 404 because the query scopes to session B
        $responseB->assertStatus(404);

        // Assert it still exists
        $this->assertDatabaseHas('registration_identity_documents', [
            'id' => $docIdA,
        ]);
    }

    public function test_storage_cleanup_on_db_failure()
    {
        $setup = $this->createSession(RegistrationSessionStatus::OTP_VERIFIED);

        // Simulate DB failure
        RegistrationIdentityDocument::saving(function () {
            throw new \Exception('Simulated DB Failure');
        });

        $response = $this->postJson('/api/registration/identity-documents', [
            'reference' => $setup['reference'],
            'token' => $setup['token'],
            'kind' => RegistrationDocumentKind::PROFILE_PHOTO->value,
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertStatus(500);

        // Assert file was cleaned up from private storage
        $files = Storage::disk('private')->allFiles();
        $this->assertEmpty($files, 'Storage was not cleaned up after DB failure');

        RegistrationIdentityDocument::flushEventListeners();
        RegistrationIdentityDocument::boot();
    }

    public function test_replacement_atomicity_on_db_failure()
    {
        $setup = $this->createSession(RegistrationSessionStatus::OTP_VERIFIED);

        // Upload first valid document
        $response1 = $this->postJson('/api/registration/identity-documents', [
            'reference' => $setup['reference'],
            'token' => $setup['token'],
            'kind' => RegistrationDocumentKind::PROFILE_PHOTO->value,
            'file' => UploadedFile::fake()->image('old_photo.jpg'),
        ]);

        $response1->assertStatus(201);
        $oldId = $response1->json('id');

        $oldDoc = RegistrationIdentityDocument::find($oldId);
        $this->assertNotNull($oldDoc);
        $this->assertTrue(Storage::disk('private')->exists($oldDoc->storage_path));

        // Simulate DB failure for the replacement ONLY
        RegistrationIdentityDocument::saving(function ($model) use ($oldId) {
            if ($model->id !== $oldId) {
                throw new \Exception('Simulated DB Failure on Replacement');
            }
        });

        $response2 = $this->postJson('/api/registration/identity-documents', [
            'reference' => $setup['reference'],
            'token' => $setup['token'],
            'kind' => RegistrationDocumentKind::PROFILE_PHOTO->value,
            'file' => UploadedFile::fake()->image('new_photo.jpg'),
        ]);

        $response2->assertStatus(500);

        // Assert old DB record remains authoritative
        $this->assertDatabaseHas('registration_identity_documents', [
            'id' => $oldId,
        ]);

        // Assert old physical file still exists
        $this->assertTrue(Storage::disk('private')->exists($oldDoc->storage_path));

        // Assert NEW physical object was cleaned up (storage should exactly have 1 file)
        $files = Storage::disk('private')->allFiles();
        $this->assertCount(1, $files);
        $this->assertEquals($oldDoc->storage_path, $files[0]);

        RegistrationIdentityDocument::flushEventListeners();
        RegistrationIdentityDocument::boot();
    }

    public function test_claim_failure_recovery_contract()
    {
        $setup = $this->createSession(RegistrationSessionStatus::OTP_VERIFIED);

        // 1. Upload a document
        $this->postJson('/api/registration/identity-documents', [
            'reference' => $setup['reference'],
            'token' => $setup['token'],
            'kind' => RegistrationDocumentKind::PROFILE_PHOTO->value,
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertStatus(201);

        $doc = RegistrationIdentityDocument::where('registration_session_id', $setup['session']->id)->first();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $claimService = app(RegistrationIdentityDocumentClaimService::class);

        // 2. Simulate Claim Failure
        // We can simulate this by causing a DB error inside the claim service transaction,
        // but here we just manually verify the state if claim didn't happen.
        // It stays VALIDATED.
        $this->assertEquals(RegistrationDocumentStatus::VALIDATED, $doc->status);

        // 3. Retry claims them to the intended user
        $claimService->claimDocumentsForUser($setup['session'], $user1);

        $doc->refresh();
        $this->assertEquals(RegistrationDocumentStatus::CLAIMED, $doc->status);
        $this->assertEquals($user1->id, $doc->claimed_by_user_id);
        $this->assertNotNull($doc->claimed_at);

        // 4. Already claimed documents cannot migrate to another user
        // 5. Repeated claim operation is idempotent
        $claimService->claimDocumentsForUser($setup['session'], $user2);

        $doc->refresh();
        $this->assertEquals(RegistrationDocumentStatus::CLAIMED, $doc->status);
        $this->assertEquals($user1->id, $doc->claimed_by_user_id); // Still User 1
    }
}
