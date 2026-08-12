<?php

namespace App\Models;

use App\Domain\Registration\Enums\RegistrationSessionStatus;
use App\Domain\Registration\Enums\RegistrationSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationSession extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'public_reference',
        'token_hash',
        'mobile_canonical',
        'email',
        'registration_source',
        'status',
        'otp_record_id',
        'otp_verified_at',
        'expires_at',
        'consumed_at',
        'last_activity_at',
        'ip_hash',
        'metadata',
    ];

    protected $casts = [
        'status' => RegistrationSessionStatus::class,
        'registration_source' => RegistrationSource::class,
        'otp_verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->status === RegistrationSessionStatus::CONSUMED || $this->consumed_at !== null;
    }

    public function isVerified(): bool
    {
        return $this->status === RegistrationSessionStatus::OTP_VERIFIED ||
            $this->status === RegistrationSessionStatus::READY_FOR_ACCOUNT_CREATION;
    }
}
