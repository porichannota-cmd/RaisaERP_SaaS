<?php

namespace App\Providers;

use App\Domain\IAM\Contracts\ScopeTargetValidator;
use App\Domain\IAM\Services\AuthorizationResolver;
use App\Domain\IAM\Services\DefaultScopeTargetValidator;
use App\Domain\Identity\Contracts\IdentityDocumentExtractionInterface;
use App\Domain\Identity\Contracts\IdentityVerificationProviderInterface;
use App\Domain\Identity\Providers\NullIdentityDocumentExtractionProvider;
use App\Domain\Identity\Providers\NullIdentityVerificationProvider;
use App\Domain\Media\Contracts\ImageOptimizerInterface;
use App\Domain\Media\Contracts\MalwareScannerInterface;
use App\Domain\Media\Services\InterventionImageOptimizer;
use App\Domain\Media\Services\NullMalwareScanner;
use App\Domain\Registration\Contracts\SensitiveDataCipherInterface;
use App\Domain\Registration\Contracts\SensitiveLookupHasherInterface;
use App\Domain\Registration\Services\HmacSensitiveLookupHasher;
use App\Domain\Registration\Services\LaravelSensitiveDataCipher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ScopeTargetValidator::class, DefaultScopeTargetValidator::class);
        $this->app->singleton(MalwareScannerInterface::class, NullMalwareScanner::class);
        $this->app->singleton(ImageOptimizerInterface::class, InterventionImageOptimizer::class);

        $this->app->singleton(SensitiveDataCipherInterface::class, LaravelSensitiveDataCipher::class);
        $this->app->singleton(SensitiveLookupHasherInterface::class, HmacSensitiveLookupHasher::class);

        // Identity Providers
        $this->app->singleton(IdentityDocumentExtractionInterface::class, function ($app) {
            // In future, resolve based on config('identity.extraction_provider')
            return new NullIdentityDocumentExtractionProvider;
        });

        $this->app->singleton(IdentityVerificationProviderInterface::class, function ($app) {
            // In future, resolve based on config('identity.verification_provider')
            return new NullIdentityVerificationProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability, $arguments = []) {
            $scopeType = null;
            $scopeId = null;

            if (count($arguments) >= 1 && is_string($arguments[0])) {
                $scopeType = $arguments[0];
                if (count($arguments) >= 2 && is_string($arguments[1])) {
                    $scopeId = $arguments[1];
                }
            }

            $resolver = app(AuthorizationResolver::class);

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
