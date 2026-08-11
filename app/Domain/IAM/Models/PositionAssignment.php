<?php

namespace App\Domain\IAM\Models;

use App\Domain\Audit\Auditable;
use App\Domain\IAM\Enums\PositionAssignmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PositionAssignment extends Model
{
    use Auditable, HasUlids;

    protected $table = 'position_assignments';

    protected $fillable = [
        'membership_id',
        'position_id',
        'reference_number',
        'status',
        'effective_from',
        'effective_to',
        'ended_by',
        'ended_reason',
    ];

    protected $casts = [
        'status' => PositionAssignmentStatus::class,
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function membership()
    {
        return $this->belongsTo(TenantMembership::class, 'membership_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function ender()
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
