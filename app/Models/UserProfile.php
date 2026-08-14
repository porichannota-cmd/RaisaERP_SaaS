<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'metadata' => 'array',
        ];
    }
}
