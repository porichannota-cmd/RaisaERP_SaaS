<?php

namespace App\Domain\IAM\Services;

use App\Domain\IAM\Enums\PositionAssignmentStatus;
use App\Domain\IAM\Enums\PositionSource;
use App\Domain\IAM\Models\Position;
use App\Domain\IAM\Models\PositionAssignment;
use App\Domain\IAM\Models\TenantMembership;

class PositionAssignmentService
{
    public function assignPosition(TenantMembership $membership, Position $position, string $referenceNumber): PositionAssignment
    {
        // 1. Cross-Tenant Security Guard
        if ($position->source !== PositionSource::SYSTEM_TEMPLATE) {
            if ($membership->tenant_id !== $position->tenant_id) {
                throw new \InvalidArgumentException('Cross-tenant position assignment denied: Membership and Position tenants do not match.');
            }
        }

        // 2. End current active assignments for this membership (if the business logic allows only one active position, or specific promotion logic)
        // Here we can enforce promotion logic. For now, just create the new assignment.

        return PositionAssignment::create([
            'membership_id' => $membership->id,
            'position_id' => $position->id,
            'reference_number' => $referenceNumber,
            'status' => PositionAssignmentStatus::ACTIVE,
            'effective_from' => now()->toDateString(),
        ]);
    }

    public function promote(TenantMembership $membership, Position $newPosition, string $newReferenceNumber, ?int $actorId = null): void
    {
        // 1. Find currently active position assignment
        $current = PositionAssignment::where('membership_id', $membership->id)
            ->where('status', PositionAssignmentStatus::ACTIVE)
            ->first();

        if ($current) {
            $current->update([
                'status' => PositionAssignmentStatus::ENDED,
                'effective_to' => now()->subDay()->toDateString(),
                'ended_by' => $actorId,
                'ended_reason' => 'Promotion to '.$newPosition->code,
            ]);
        }

        // 2. Assign new position
        $this->assignPosition($membership, $newPosition, $newReferenceNumber);
    }
}
