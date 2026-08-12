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
use App\Domain\Tenant\ActiveTenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MediaSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_upload(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmp, 'dummy content');
        $file = new UploadedFile($tmp, 'avatar.jpg', 'image/jpeg', null, true);

        $response = $this->post(route('api.media.store'), [
            'file' => $file,
            'kind' => MediaKind::IMAGE->value,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(500);
        $response->assertJsonFragment(['error' => 'AuthenticationException']);
    }

    public function test_user_without_permission_cannot_upload(): void
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        $user = User::factory()->create();

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmp, 'dummy content');
        $file = new UploadedFile($tmp, 'avatar.jpg', 'image/jpeg', null, true);

        ActiveTenantContext::set($tenant->id);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('api.media.store'), [
                'file' => $file,
                'kind' => MediaKind::IMAGE->value,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(403);
    }

    public function test_cannot_upload_php_file_disguised_as_image(): void
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        $user = User::factory()->create();
        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Uploader',
            'type' => RoleType::TENANT_CUSTOM->value,
        ]);

        $service = new MembershipRoleService;
        $service->assignRole($membership, $role);

        Permission::create(['key' => 'media.upload', 'description' => 'Upload']);

        AuthorizationGrant::create([
            'role_id' => $role->id,
            'permission_key' => 'media.upload',
            'scope_type' => AuthScope::TENANT->value,
            'scope_id' => $tenant->id,
            'effective_from' => now(),
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmp, '<?php echo "Hello World"; ?>');
        $file = new UploadedFile($tmp, 'shell.php.jpg', 'image/jpeg', null, true);

        ActiveTenantContext::set($tenant->id);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->post(route('api.media.store'), [
                'file' => $file,
                'kind' => MediaKind::IMAGE->value,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }
}
