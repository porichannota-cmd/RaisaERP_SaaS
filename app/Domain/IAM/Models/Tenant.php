<?php

namespace App\Domain\IAM\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use Auditable, HasUlids;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'status',
    ];

    public function memberships()
    {
        return $this->hasMany(TenantMembership::class);
    }
}
