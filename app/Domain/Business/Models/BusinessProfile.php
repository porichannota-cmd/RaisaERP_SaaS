<?php

namespace App\Domain\Business\Models;

use App\Domain\Business\Enums\ProvisioningStatus;
use App\Models\User;
use App\Domain\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'legal_name',
        'display_name',
        'trade_license_encrypted',
        'trade_license_fingerprint',
        'tin_encrypted',
        'tin_fingerprint',
        'bin_encrypted',
        'bin_fingerprint',
    ];

    protected $casts = [
        'provisioning_status' => ProvisioningStatus::class,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function address(): HasOne
    {
        return $this->hasOne(BusinessAddress::class, 'business_profile_id');
    }
}
