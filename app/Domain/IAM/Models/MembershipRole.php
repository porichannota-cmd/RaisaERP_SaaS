<?php

namespace App\Domain\IAM\Models;

use App\Domain\Audit\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class MembershipRole extends Model
{
    use Auditable, HasUlids;

    protected $table = 'membership_roles';

    protected $fillable = [
        'membership_id',
        'role_id',
        'assigned_by',
        'assigned_at',
        'effective_from',
        'effective_until',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'revoked_at' => 'datetime',
    ];

    public function membership()
    {
        return $this->belongsTo(TenantMembership::class, 'membership_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
