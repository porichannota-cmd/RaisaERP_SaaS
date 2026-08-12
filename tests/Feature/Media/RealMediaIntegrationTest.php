<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Domain\IAM\Enums\AuthScope;
use App\Domain\IAM\Enums\RoleType;
use App\Domain\IAM\Models\AuthorizationGrant;
use App\Domain\IAM\Models\Permission;
use App\Domain\IAM\Models\Role;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\IAM\Services\MembershipRoleService;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Tenant\ActiveTenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealMediaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'HQ']);
        $this->user = User::factory()->create();

        ActiveTenantContext::set($this->tenant->id);

        $membership = TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Uploader',
            'type' => RoleType::TENANT_CUSTOM->value,
            'is_system_role' => false,
        ]);

        $service = new MembershipRoleService;
        $service->assignRole($membership, $role);

        $uploadPermission = Permission::firstOrCreate(['key' => 'media.upload'], ['description' => 'Upload media']);

        AuthorizationGrant::create([
            'role_id' => $role->id,
            'permission_key' => $uploadPermission->key,
            'scope_type' => AuthScope::TENANT->value,
            'scope_id' => $this->tenant->id,
            'effective_from' => now(),
        ]);

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_real_jpeg_upload_and_webp_conversion(): void
    {
        $fixturePath = base_path('tests/Fixtures/Media/valid.jpg');
        $file = new UploadedFile($fixturePath, 'valid.jpg', 'image/jpeg', null, true);

        $response = $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('api.media.store'), [
                'file' => $file,
                'kind' => MediaKind::IMAGE->value,
            ]);

        $response->assertStatus(201);
        $assetId = $response->json('id');
        $asset = MediaAsset::find($assetId);

        $this->assertNotNull($asset);
        $this->assertEquals('image/webp', $asset->mime_type);
        $this->assertEquals('webp', $asset->extension);
        $this->assertEquals(MediaVisibility::PUBLIC, $asset->visibility);
        $this->assertNotNull($asset->metadata);
        $this->assertEquals(100, $asset->metadata['width']);
        $this->assertEquals(100, $asset->metadata['height']);

        Storage::disk('public')->assertExists($asset->storage_path);

        $outputBytes = Storage::disk('public')->get($asset->storage_path);
        $this->assertTrue(str_starts_with($outputBytes, 'RIFF'));
        $this->assertStringContainsString('WEBPVP8', substr($outputBytes, 0, 16));

        $expectedHash = hash('sha256', $outputBytes);
        $this->assertEquals($expectedHash, $asset->checksum_sha256);
    }

    public function test_real_png_upload_and_webp_conversion(): void
    {
        $fixturePath = base_path('tests/Fixtures/Media/valid.png');
        $file = new UploadedFile($fixturePath, 'valid.png', 'image/png', null, true);

        $response = $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('api.media.store'), [
                'file' => $file,
                'kind' => MediaKind::IMAGE->value,
            ]);

        $response->assertStatus(201);
        $assetId = $response->json('id');
        $asset = MediaAsset::find($assetId);

        $this->assertEquals('image/webp', $asset->mime_type);
        $this->assertEquals('webp', $asset->extension);
    }

    public function test_real_webp_upload(): void
    {
        $fixturePath = base_path('tests/Fixtures/Media/valid.webp');
        $file = new UploadedFile($fixturePath, 'valid.webp', 'image/webp', null, true);

        $response = $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('api.media.store'), [
                'file' => $file,
                'kind' => MediaKind::IMAGE->value,
            ]);

        $response->assertStatus(201);
        $assetId = $response->json('id');
        $asset = MediaAsset::find($assetId);

        $this->assertEquals('image/webp', $asset->mime_type);
    }

    public function test_storage_cleanup_on_db_failure(): void
    {
        $fixturePath = base_path('tests/Fixtures/Media/valid.jpg');
        $file = new UploadedFile($fixturePath, 'valid.jpg', 'image/jpeg', null, true);

        MediaAsset::saving(function () {
            throw new \Exception('Simulated DB Failure');
        });

        $response = $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('api.media.store'), [
                'file' => $file,
                'kind' => MediaKind::IMAGE->value,
            ]);

        $response->assertStatus(500);

        // Verify storage is empty (cleanup succeeded)
        $files = Storage::disk('public')->allFiles();
        $this->assertEmpty($files, 'Storage was not cleaned up after DB failure');

        // Remove the event listener for subsequent tests
        MediaAsset::flushEventListeners();
        MediaAsset::boot();
    }
}
