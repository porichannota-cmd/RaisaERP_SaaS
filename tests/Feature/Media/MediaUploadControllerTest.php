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
use App\Domain\Media\Contracts\ImageOptimizerInterface;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Services\MediaValidationPolicy;
use App\Domain\Tenant\ActiveTenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaUploadControllerTest extends TestCase
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
        $viewPermission = Permission::firstOrCreate(['key' => 'media.view'], ['description' => 'View media']);

        AuthorizationGrant::create([
            'role_id' => $role->id,
            'permission_key' => $uploadPermission->key,
            'scope_type' => AuthScope::TENANT->value,
            'scope_id' => $this->tenant->id,
            'effective_from' => now(),
        ]);

        AuthorizationGrant::create([
            'role_id' => $role->id,
            'permission_key' => $viewPermission->key,
            'scope_type' => AuthScope::TENANT->value,
            'scope_id' => $this->tenant->id,
            'effective_from' => now(),
        ]);

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_user_can_upload_valid_image(): void
    {
        $policyMock = $this->createMock(MediaValidationPolicy::class);
        $policyMock->expects($this->once())->method('validate');
        $policyMock->expects($this->once())->method('getDefaultVisibility')->willReturn(MediaVisibility::PUBLIC);
        $this->app->instance(MediaValidationPolicy::class, $policyMock);

        // Mock the image optimizer to avoid needing the GD extension in CLI
        $optimizerMock = $this->createMock(ImageOptimizerInterface::class);
        $optimizerMock->expects($this->once())
            ->method('optimize')
            ->willReturn([
                'width' => 100,
                'height' => 100,
                'size' => 1234,
                'mime' => 'image/webp',
                'extension' => 'webp',
            ]);

        $this->app->instance(ImageOptimizerInterface::class, $optimizerMock);

        // We can create a fake file that mimics an image but doesn't require GD to generate
        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->postJson(route('api.media.store'), [
                'file' => $file,
                'kind' => MediaKind::IMAGE->value,
            ]);

        if ($response->status() !== 201) {
            dump($response->json());
        }
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id', 'original_filename', 'media_kind', 'visibility', 'processing_status', 'created_at',
        ]);

        $assetId = $response->json('id');
        $asset = MediaAsset::find($assetId);

        $this->assertNotNull($asset);
        $this->assertEquals($this->tenant->id, $asset->tenant_id);
        $this->assertEquals('image/webp', $asset->mime_type); // Because Optimizer converts to WebP
        $this->assertEquals('webp', $asset->extension);

        // Default visibility for IMAGE is public
        $this->assertEquals(MediaVisibility::PUBLIC, $asset->visibility);

        Storage::disk('public')->assertExists($asset->storage_path);
    }

    public function test_cross_tenant_access_denied(): void
    {
        $otherTenant = Tenant::create(['name' => 'Branch']);

        $asset = MediaAsset::forceCreate([
            'id' => Str::ulid()->toString(),
            'tenant_id' => $otherTenant->id,
            'uploaded_by' => $this->user->id,
            'original_filename' => 'secret.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'tenants/'.$otherTenant->id.'/media/private/2026/08/test.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1024,
            'checksum_sha256' => 'xyz',
            'media_kind' => MediaKind::DOCUMENT->value,
            'visibility' => MediaVisibility::PRIVATE,
            'processing_status' => 'ready',
            'security_status' => 'clean',
        ]);

        Storage::disk('local')->put($asset->storage_path, 'dummy content');

        $response = $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('api.media.show', ['id' => $asset->id]));

        // Should be forbidden because active tenant doesn't own this asset
        $response->assertStatus(403);
    }
}
