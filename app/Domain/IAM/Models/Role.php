<?php

namespace App\Domain\IAM\Models;

use App\Domain\Audit\Auditable;
use App\Domain\IAM\Enums\RoleType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use Auditable, HasUlids;

    protected $table = 'roles';

    protected $fillable = [
        'tenant_id',
        'type',
        'name',
        'code',
        'is_system',
    ];

    protected $casts = [
        'type' => RoleType::class,
        'is_system' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function ($role) {
            if ($role->type === RoleType::PLATFORM_SYSTEM && $role->tenant_id !== null) {
                throw new \InvalidArgumentException('A platform_system role MUST have a null tenant_id.');
            }
            if ($role->type !== RoleType::PLATFORM_SYSTEM && $role->tenant_id === null) {
                throw new \InvalidArgumentException('Tenant roles MUST have a non-null tenant_id.');
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function grants()
    {
        return $this->hasMany(AuthorizationGrant::class, 'role_id');
    }
}
