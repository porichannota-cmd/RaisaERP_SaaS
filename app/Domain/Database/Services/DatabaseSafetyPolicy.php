<?php

namespace App\Domain\Database\Services;

use Illuminate\Support\Facades\App;

class DatabaseSafetyPolicy
{
    private string $denyReason = '';

    public function isDestructiveCommandAllowed(): bool
    {
        $env = App::environment();
        $connectionName = config('database.default');

        $dbName = config("database.connections.{$connectionName}.database");
        $host = config("database.connections.{$connectionName}.host");

        // SQLite uses :memory: or file path, not standard db names, but we still apply safety.
        if ($connectionName === 'sqlite' && $dbName === ':memory:') {
            // Memory is always safe
            return true;
        }

        if (empty($dbName)) {
            $this->denyReason = 'Target database name is missing or empty.';
            return false;
        }

        // Strict registry of protected databases
        $protectedDatabases = ['raisa_erp', 'raisa_erp_production', 'raisa_erp_staging'];

        $envProtected = env('DB_PROTECTED_DATABASES', '');
        if ($envProtected) {
            $protectedDatabases = array_merge($protectedDatabases, array_map('trim', explode(',', $envProtected)));
        }

        if (in_array($dbName, $protectedDatabases, true)) {
            $this->denyReason = "Target database '{$dbName}' is in the protected database registry.";
            return false;
        }

        if ($env !== 'testing') {
            $this->denyReason = "Destructive operations require the 'testing' environment. Current environment is '{$env}'.";
            return false;
        }

        // Registry of explicitly allowed test databases
        $allowlist = ['raisa_erp_wave1b_test', 'raisa_erp_wave1a_test', 'raisa_erp_test'];

        $envAllowed = env('DB_ALLOWED_TEST_DATABASES', '');
        if ($envAllowed) {
            $allowlist = array_merge($allowlist, array_map('trim', explode(',', $envAllowed)));
        }

        // Must either match testing pattern or be explicitly allowlisted
        $isAllowedName = in_array($dbName, $allowlist, true)
            || str_ends_with((string)$dbName, '_test')
            || str_ends_with((string)$dbName, '_testing');

        if (!$isAllowedName) {
            $this->denyReason = "Target database '{$dbName}' does not match testing naming conventions and is not allowlisted.";
            return false;
        }

        // Host safety
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1', 'mariadb', 'mysql', ''], true) && $connectionName !== 'sqlite') {
             $this->denyReason = "Host '{$host}' is not a recognized local/isolated testing host.";
             return false;
        }

        return true;
    }

    public function getDenyReason(): string
    {
        return $this->denyReason;
    }

    public function getSafeIdentity(): array
    {
        $connectionName = config('database.default');
        $dbName = config("database.connections.{$connectionName}.database");

        $protectedDatabases = ['raisa_erp', 'raisa_erp_production', 'raisa_erp_staging'];
        $envProtected = env('DB_PROTECTED_DATABASES', '');
        if ($envProtected) {
            $protectedDatabases = array_merge($protectedDatabases, array_map('trim', explode(',', $envProtected)));
        }

        $allowlist = ['raisa_erp_wave1b_test', 'raisa_erp_wave1a_test', 'raisa_erp_test'];
        $envAllowed = env('DB_ALLOWED_TEST_DATABASES', '');
        if ($envAllowed) {
            $allowlist = array_merge($allowlist, array_map('trim', explode(',', $envAllowed)));
        }

        $isApprovedTestDatabase = (in_array($dbName, $allowlist, true)
            || str_ends_with((string)$dbName, '_test')
            || str_ends_with((string)$dbName, '_testing')) && !in_array($dbName, $protectedDatabases, true);

        return [
            'environment' => App::environment(),
            'connection' => $connectionName,
            'database' => $dbName,
            'host' => config("database.connections.{$connectionName}.host"),
            'isProtected' => in_array($dbName, $protectedDatabases, true),
            'isApprovedTestDatabase' => $isApprovedTestDatabase,
            'destructiveCommandsAllowed' => $this->isDestructiveCommandAllowed(),
        ];
    }
}
