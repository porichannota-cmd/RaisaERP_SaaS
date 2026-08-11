<?php

namespace Tests\Unit\IAM;

use App\Domain\IAM\Enums\MembershipStatus;
use App\Domain\IAM\Enums\PositionAssignmentStatus;
use App\Domain\IAM\Enums\PositionSource;
use App\Domain\IAM\Models\Position;
use App\Domain\IAM\Models\PositionAssignment;
use App\Domain\IAM\Models\Tenant;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\IAM\Services\PositionAssignmentService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PositionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_promotion_ends_old_assignment_and_creates_new()
    {
        $tenant = Tenant::create(['name' => 'HQ']);
        $user = User::factory()->create();

        $membership = TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $pos1 = Position::create([
            'tenant_id' => $tenant->id,
            'source' => PositionSource::TENANT_CUSTOM,
            'code' => 'JUNIOR',
            'name' => 'Junior Developer',
        ]);

        $pos2 = Position::create([
            'tenant_id' => $tenant->id,
            'source' => PositionSource::TENANT_CUSTOM,
            'code' => 'SENIOR',
            'name' => 'Senior Developer',
        ]);

        $service = new PositionAssignmentService;

        $assignment1 = $service->assignPosition($membership, $pos1, 'REF-001');

        $this->assertEquals(PositionAssignmentStatus::ACTIVE, $assignment1->status);

        $service->promote($membership, $pos2, 'REF-002', $user->id);

        $assignment1->refresh();

        $this->assertEquals(PositionAssignmentStatus::ENDED, $assignment1->status);
        $this->assertNotNull($assignment1->effective_to);
        $this->assertEquals($user->id, $assignment1->ended_by);
        $this->assertStringContainsString('Promotion to SENIOR', $assignment1->ended_reason);

        $activeAssignments = PositionAssignment::where('membership_id', $membership->id)
            ->where('status', PositionAssignmentStatus::ACTIVE)
            ->get();

        $this->assertCount(1, $activeAssignments);
        $this->assertEquals($pos2->id, $activeAssignments->first()->position_id);
        $this->assertEquals('REF-002', $activeAssignments->first()->reference_number);
    }
}
