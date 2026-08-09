<?php

namespace App\Domain\Events;

use JsonSerializable;

interface DomainEvent extends JsonSerializable
{
    /**
     * The unique identifier for this specific event occurrence.
     */
    public function eventId(): string;

    /**
     * The type name of the event (e.g. 'UserRegistered', 'InvoicePaid').
     */
    public function eventType(): string;

    /**
     * The date and time the event occurred.
     */
    public function occurredAt(): \DateTimeImmutable;
    
    /**
     * Return the event payload as an array.
     */
    public function payload(): array;
    
    /**
     * Returns the version of this event's schema.
     */
    public function version(): int;
}
