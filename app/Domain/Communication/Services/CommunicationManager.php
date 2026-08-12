<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Contracts\EmailProviderInterface;
use App\Domain\Communication\Contracts\SmsProviderInterface;
use App\Domain\Communication\Exceptions\OtpException;
use App\Domain\Communication\Providers\Email\SmtpEmailProvider;
use App\Domain\Communication\Providers\Sms\LogSmsProvider;
use App\Domain\Communication\Providers\Sms\MimSmsProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Provider resolver / communication manager.
 * Resolves the correct SMS or Email provider from config.
 * Enforces production guard for LogSmsProvider.
 */
class CommunicationManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function smsProvider(?string $providerKey = null): SmsProviderInterface
    {
        $key = $providerKey ?? config('otp.default_sms_provider', 'log');

        $this->assertProductionGuard($key);

        return $this->resolveSmsProvider($key);
    }

    public function emailProvider(?string $providerKey = null): EmailProviderInterface
    {
        $key = $providerKey ?? config('otp.default_email_provider', 'smtp');

        return $this->resolveEmailProvider($key);
    }

    private function resolveSmsProvider(string $key): SmsProviderInterface
    {
        $config = config("otp.sms_providers.{$key}");

        if (! $config) {
            Log::error('Unknown SMS provider requested', ['key' => $key]);
            throw OtpException::deliveryFailed("Unknown SMS provider: {$key}");
        }

        return match ($config['driver']) {
            'log' => $this->container->make(LogSmsProvider::class),
            'mim' => $this->container->make(MimSmsProvider::class, [
                'apiUrl' => $config['api_url'] ?? null,
                'username' => $config['username'] ?? null,
                'password' => $config['password'] ?? null,
                'senderId' => $config['sender'] ?? null,
            ]),
            default => throw OtpException::deliveryFailed("Unsupported SMS driver: {$config['driver']}"),
        };
    }

    private function resolveEmailProvider(string $key): EmailProviderInterface
    {
        return match ($key) {
            'smtp' => $this->container->make(SmtpEmailProvider::class),
            default => throw OtpException::deliveryFailed("Unsupported email provider: {$key}"),
        };
    }

    /**
     * Production guard: prevent LogSmsProvider from being used in production.
     */
    private function assertProductionGuard(string $key): void
    {
        if (
            $key === 'log'
            && app()->environment('production')
            && config('otp.production_log_provider_guard', true)
        ) {
            throw OtpException::deliveryFailed(
                'Log SMS provider is not permitted in production environment.'
            );
        }
    }
}
