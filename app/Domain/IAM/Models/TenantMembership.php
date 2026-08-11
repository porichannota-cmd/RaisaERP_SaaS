<?php

namespace App\Domain\IAM\Models;

use App\Domain\Audit\Auditable;
use App\Domain\IAM\Enums\MembershipStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TenantMembership extends Model
{
    use Auditable, HasUlids;

    protected $table = 'tenant_memberships';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
    ];

    protected $casts = [
        'status' => MembershipStatus::class,
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function membershipRoles()
    {
        return $this->hasMany(MembershipRole::class, 'membership_id');
    }

    public function positionAssignments()
    {
        return $this->hasMany(PositionAssignment::class, 'membership_id');
    }
}
