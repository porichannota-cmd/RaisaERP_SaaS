<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdentityVerificationAttempt extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_identity_verification_id',
        'user_id',
        'provider',
        'operation',
        'status',
        'provider_reference',
        'failure_code',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verification()
    {
        return $this->belongsTo(UserIdentityVerification::class, 'user_identity_verification_id');
    }
}
