<?php

namespace App\Domain\Events\Outbox;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'type',
        'payload',
        'correlation_id',
        'causation_id',
        'actor_id',
        'status',
        'attempts',
        'error',
        'occurred_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
