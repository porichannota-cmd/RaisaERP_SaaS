<?php

namespace App\Domain\Events;

use DateTimeImmutable;
use Illuminate\Support\Str;

abstract class BaseDomainEvent implements DomainEvent
{
    private string $eventId;
    private DateTimeImmutable $occurredAt;
    private int $version;

    public function __construct(int $version = 1)
    {
        $this->eventId = Str::uuid()->toString();
        $this->occurredAt = new DateTimeImmutable();
        $this->version = $version;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function eventType(): string
    {
        // By default, use the short class name as the event type
        return (new \ReflectionClass($this))->getShortName();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function jsonSerialize(): array
    {
        return [
            'event_id' => $this->eventId(),
            'event_type' => $this->eventType(),
            'occurred_at' => $this->occurredAt()->format('Y-m-d\TH:i:s.u\Z'),
            'version' => $this->version(),
            'payload' => $this->payload(),
        ];
    }
}
