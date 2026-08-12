<?php

namespace App\Providers;

use App\Domain\Communication\Services\CommunicationManager;
use App\Domain\Communication\Services\DestinationNormalizer;
use App\Domain\Communication\Services\OtpService;
use Illuminate\Support\ServiceProvider;

/**
 * Wave 1C Communication Service Provider.
 * Registered separately from AppServiceProvider to keep frozen certified code unmodified.
 */
class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DestinationNormalizer::class);
        $this->app->singleton(CommunicationManager::class);
        $this->app->singleton(OtpService::class);
    }

    public function boot(): void
    {
        // Future: register scheduled cleanup job for expired OTP records
    }
}
