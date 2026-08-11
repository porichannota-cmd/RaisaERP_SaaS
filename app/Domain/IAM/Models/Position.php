<?php

namespace App\Domain\IAM\Models;

use App\Domain\Audit\Auditable;
use App\Domain\IAM\Enums\PositionSource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use Auditable, HasUlids;

    protected $table = 'positions';

    protected $fillable = [
        'tenant_id',
        'source',
        'code',
        'name',
        'status',
    ];

    protected $casts = [
        'source' => PositionSource::class,
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
