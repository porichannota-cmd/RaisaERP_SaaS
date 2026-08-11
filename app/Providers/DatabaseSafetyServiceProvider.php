<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use App\Domain\Database\Services\DatabaseSafetyPolicy;

class DatabaseSafetyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DatabaseSafetyPolicy::class, function ($app) {
            return new DatabaseSafetyPolicy();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Intercept artisan commands to block destructive database mutations
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            $destructiveCommands = [
                'migrate:fresh',
                'migrate:reset',
                'migrate:rollback',
                'db:wipe',
                'migrate:refresh'
            ];

            if (in_array($event->command, $destructiveCommands, true)) {
                $policy = $this->app->make(DatabaseSafetyPolicy::class);

                if (!$policy->isDestructiveCommandAllowed()) {
                    $this->abortCommand($event->command, $policy->getDenyReason());
                }
            }
        });
    }

    private function abortCommand(string $command, string $reason): void
    {
        echo "DATABASE_SAFETY_BLOCKED\n";
        echo "Command: {$command}\n";
        echo "Reason: {$reason}\n";

        $dbName = config('database.connections.' . config('database.default') . '.database');
        $env = app()->environment();

        echo "Environment: {$env}\n";
        echo "Resolved Database: {$dbName}\n";

        exit(1);
    }
}
