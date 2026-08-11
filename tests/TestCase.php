<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $policy = $this->app->make(\App\Domain\Database\Services\DatabaseSafetyPolicy::class);
        if (!$policy->isDestructiveCommandAllowed()) {
            echo "\nDATABASE_SAFETY_BLOCKED\n";
            echo "Test suite attempted to boot with a non-isolated or protected database target.\n";
            echo "Reason: " . $policy->getDenyReason() . "\n";

            $dbName = config('database.connections.' . config('database.default') . '.database');
            echo "Environment: " . app()->environment() . "\n";
            echo "Resolved Database: {$dbName}\n";
            exit(1);
        }
    }
}
