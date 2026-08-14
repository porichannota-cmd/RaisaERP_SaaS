<?php

namespace App\Models;

use App\Domain\Identity\Enums\IdentityVerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserIdentityVerification extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'document_type',
        'status',
        'provider',
        'verified_at',
        'last_attempt_at',
        'manual_review_required',
        'extracted_data_encrypted',
        'normalized_name',
        'normalized_dob',
        'nid_number_encrypted',
        'nid_number_fingerprint',
        'provider_reference',
        'failure_code',
        'metadata',
    ];

    protected $hidden = [
        'extracted_data_encrypted',
        'nid_number_encrypted',
        'nid_number_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'status' => IdentityVerificationStatus::class,
            'verified_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'manual_review_required' => 'boolean',
            'normalized_dob' => 'date',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attempts()
    {
        return $this->hasMany(IdentityVerificationAttempt::class);
    }
}
