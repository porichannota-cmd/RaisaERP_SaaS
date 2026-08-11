<?php

namespace App\Domain\IAM\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    // Permissions use string keys (e.g., 'users.view')
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'description',
    ];
}
