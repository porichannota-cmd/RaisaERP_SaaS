<?php

namespace Tests\Unit\Events;

use App\Domain\Events\BaseDomainEvent;
use App\Domain\Events\Outbox\OutboxEvent;
use App\Domain\Events\Outbox\OutboxPublisher;
use App\Domain\Tenant\ActiveTenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DummyEvent extends BaseDomainEvent
{
    public function payload(): array
    {
        return ['foo' => 'bar'];
    }
}

class OutboxPublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ActiveTenantContext::clear();
        parent::tearDown();
    }

    public function test_it_refuses_to_publish_outside_transaction()
    {
        // RefreshDatabase starts a transaction, so we rollback to hit level 0
        DB::rollBack();
        
        try {
            $publisher = new OutboxPublisher();
            $event = new DummyEvent();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('transaction');

            $publisher->publish($event);
        } finally {
            // Restore transaction for RefreshDatabase trait teardown
            DB::beginTransaction();
        }
    }

    public function test_it_refuses_to_publish_without_tenant_context()
    {
        $publisher = new OutboxPublisher();
        $event = new DummyEvent();

        DB::beginTransaction();
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('No active tenant context');
            $publisher->publish($event);
        } finally {
            DB::rollBack();
        }
    }

    public function test_it_publishes_event_in_outbox()
    {
        $publisher = new OutboxPublisher();
        $event = new DummyEvent();

        ActiveTenantContext::set('TENANT-A');

        DB::transaction(function () use ($publisher, $event) {
            $publisher->publish($event, 'cause-123', 'actor-456');
        });

        $this->assertDatabaseHas('outbox_events', [
            'id' => $event->eventId(),
            'tenant_id' => 'TENANT-A',
            'type' => 'DummyEvent',
            'causation_id' => 'cause-123',
            'actor_id' => 'actor-456',
            'status' => 'pending',
        ]);

        $record = OutboxEvent::find($event->eventId());
        $this->assertEquals(['foo' => 'bar'], $record->payload['payload']);
    }

    public function test_event_is_rolled_back_with_transaction()
    {
        $publisher = new OutboxPublisher();
        $event = new DummyEvent();

        ActiveTenantContext::set('TENANT-B');

        try {
            DB::transaction(function () use ($publisher, $event) {
                $publisher->publish($event);
                throw new \Exception('Trigger rollback');
            });
        } catch (\Exception $e) {
            // expected
        }

        $this->assertDatabaseMissing('outbox_events', [
            'id' => $event->eventId(),
        ]);
    }
}
