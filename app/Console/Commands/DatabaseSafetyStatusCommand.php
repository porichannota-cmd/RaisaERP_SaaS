<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Database\Services\DatabaseSafetyPolicy;

class DatabaseSafetyStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:safety-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the current database safety status and allowed operations';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseSafetyPolicy $policy)
    {
        $identity = $policy->getSafeIdentity();

        $this->info("--- DATABASE SAFETY STATUS ---");
        $this->line("Environment: " . $identity['environment']);
        $this->line("Connection: " . $identity['connection']);
        $this->line("Database: " . $identity['database']);
        $this->line("Host Classification: " . $identity['host']);
        $this->line("Protected Database: " . ($identity['isProtected'] ? 'YES' : 'NO'));
        $this->line("Approved Test Database: " . ($identity['isApprovedTestDatabase'] ? 'YES' : 'NO'));

        if ($identity['destructiveCommandsAllowed']) {
            $this->info("Destructive Commands Allowed: YES");
        } else {
            $this->error("Destructive Commands Allowed: NO");
            $this->error("Reason: " . $policy->getDenyReason());
        }
    }
}
