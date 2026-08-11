<?php

namespace App\Domain\IAM\Models;

use App\Domain\Audit\Auditable;
use App\Domain\IAM\Enums\AuthScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuthorizationGrant extends Model
{
    use Auditable, HasUlids;

    protected $table = 'authorization_grants';

    protected $fillable = [
        'role_id',
        'permission_key',
        'scope_type',
        'scope_id',
        'constraints',
        'effective_from',
        'effective_until',
        'revoked_at',
    ];

    protected $casts = [
        'scope_type' => AuthScope::class,
        'constraints' => 'array',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'revoked_at' => 'datetime',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class, 'permission_key', 'key');
    }
}
