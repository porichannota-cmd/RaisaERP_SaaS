<?php

namespace App\Domain\Events\Outbox;

use App\Domain\Events\DomainEvent;
use App\Domain\Tenant\ActiveTenantContext;
use Illuminate\Support\Facades\DB;

class OutboxPublisher
{
    /**
     * Publish a domain event to the outbox.
     * This MUST be called within an existing database transaction for transactional guarantee.
     * 
     * @param DomainEvent $event The event to publish
     * @param string|null $causationId ID of the event/command that caused this
     * @param string|null $actorId ID of the user who triggered this
     */
    public function publish(DomainEvent $event, ?string $causationId = null, ?string $actorId = null): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \RuntimeException('Domain events must be published within an active database transaction.');
        }

        // Must always have an active tenant context according to architecture
        $tenantId = ActiveTenantContext::get();
        
        // Grab correlation ID from current context if available
        $correlationId = request()->header('X-Correlation-ID');

        OutboxEvent::create([
            'id' => $event->eventId(),
            'tenant_id' => $tenantId,
            'type' => $event->eventType(),
            'payload' => $event->jsonSerialize(),
            'correlation_id' => $correlationId,
            'causation_id' => $causationId,
            'actor_id' => $actorId,
            'status' => 'pending',
            'occurred_at' => $event->occurredAt(),
        ]);
    }
}
