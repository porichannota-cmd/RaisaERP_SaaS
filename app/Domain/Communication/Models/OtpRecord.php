<?php

namespace App\Domain\Communication\Models;

use App\Domain\Communication\Enums\DestinationType;
use App\Domain\Communication\Enums\OtpChannel;
use App\Domain\Communication\Enums\OtpPurpose;
use App\Domain\Communication\Enums\OtpStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * OTP Record model.
 *
 * SECURITY:
 *  - 'code_hash' is NEVER cast to a visible attribute.
 *  - 'code_hash' is hidden from serialization.
 *  - Plaintext OTP code is never stored here.
 */
class OtpRecord extends Model
{
    use HasUlids;

    protected $table = 'otp_records';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'destination_type',
        'destination_canonical',
        'destination_hash',
        'purpose',
        'channel',
        'provider',
        'code_hash',
        'status',
        'attempt_count',
        'send_count',
        'max_attempts',
        'expires_at',
        'verified_at',
        'consumed_at',
        'last_sent_at',
        'metadata',
    ];

    /**
     * code_hash is never exposed in JSON serialization or array output.
     */
    protected $hidden = ['code_hash'];

    protected $casts = [
        'destination_type' => DestinationType::class,
        'purpose' => OtpPurpose::class,
        'channel' => OtpChannel::class,
        'status' => OtpStatus::class,
        'attempt_count' => 'integer',
        'send_count' => 'integer',
        'max_attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isLocked(): bool
    {
        return $this->status === OtpStatus::LOCKED;
    }

    public function isConsumed(): bool
    {
        return in_array($this->status, [OtpStatus::CONSUMED, OtpStatus::VERIFIED]);
    }

    public function isActive(): bool
    {
        return $this->status->isActive() && ! $this->isExpired();
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempt_count >= $this->max_attempts;
    }
}
