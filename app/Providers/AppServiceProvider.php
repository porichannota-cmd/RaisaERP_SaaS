<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Domain\IAM\Contracts\ScopeTargetValidator::class, \App\Domain\IAM\Services\DefaultScopeTargetValidator::class);
        $this->app->singleton(\App\Domain\Media\Contracts\MalwareScannerInterface::class, \App\Domain\Media\Services\NullMalwareScanner::class);
        $this->app->singleton(\App\Domain\Media\Contracts\ImageOptimizerInterface::class, \App\Domain\Media\Services\InterventionImageOptimizer::class);

        $this->app->singleton(\App\Domain\Registration\Contracts\SensitiveDataCipherInterface::class, \App\Domain\Registration\Services\LaravelSensitiveDataCipher::class);
        $this->app->singleton(\App\Domain\Registration\Contracts\SensitiveLookupHasherInterface::class, \App\Domain\Registration\Services\HmacSensitiveLookupHasher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability, $arguments = []) {
            $scopeType = null;
            $scopeId = null;

            if (count($arguments) >= 1 && is_string($arguments[0])) {
                $scopeType = $arguments[0];
                if (count($arguments) >= 2 && is_string($arguments[1])) {
                    $scopeId = $arguments[1];
                }
            }

            $resolver = app(\App\Domain\IAM\Services\AuthorizationResolver::class);

            if ($resolver->check($user, $ability, $scopeType, $scopeId)) {
                return true;
            }

            if ($resolver->isAuthoritative($ability)) {
                return false;
            }

            return null;
        });
    }
}
