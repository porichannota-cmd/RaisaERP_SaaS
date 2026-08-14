<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserConsent extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
