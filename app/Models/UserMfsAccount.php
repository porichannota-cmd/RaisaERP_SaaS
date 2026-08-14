<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMfsAccount extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
