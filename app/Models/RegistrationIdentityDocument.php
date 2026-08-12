<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Registration\Enums\RegistrationDocumentKind;
use App\Domain\Registration\Enums\RegistrationDocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationIdentityDocument extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'registration_session_id',
        'kind',
        'storage_disk',
        'storage_path',
        'original_filename_safe',
        'detected_mime',
        'size_bytes',
        'checksum_sha256',
        'width',
        'height',
        'status',
        'expires_at',
        'claimed_by_user_id',
        'claimed_at',
        'metadata',
    ];

    protected $casts = [
        'kind' => RegistrationDocumentKind::class,
        'status' => RegistrationDocumentStatus::class,
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function registrationSession(): BelongsTo
    {
        return $this->belongsTo(RegistrationSession::class, 'registration_session_id', 'id');
    }

    public function isClaimed(): bool
    {
        return $this->status === RegistrationDocumentStatus::CLAIMED || $this->claimed_by_user_id !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
