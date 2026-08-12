<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $resolver = app(\App\Domain\Registration\Services\LoginIdentifierResolver::class);
        $accessPolicy = app(\App\Domain\Registration\Policies\AccountAccessPolicy::class);

        $this->ensureIsNotRateLimited($resolver);

        $credentials = $resolver->resolveCredentials($this->string('identifier')->toString(), $this->string('password')->toString());
        // DEBUG: uncomment if needed: \Illuminate\Support\Facades\Log::info(json_encode($credentials));

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($resolver));

            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        try {
            $accessPolicy->ensureCanAuthenticate($user);
        } catch (ValidationException $e) {
            Auth::logout();
            throw $e;
        }

        RateLimiter::clear($this->throttleKey($resolver));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(\App\Domain\Registration\Services\LoginIdentifierResolver $resolver): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($resolver), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey($resolver));

        throw ValidationException::withMessages([
            'identifier' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(\App\Domain\Registration\Services\LoginIdentifierResolver $resolver): string
    {
        $canonicalIdentifier = $resolver->resolveRateLimitKey($this->string('identifier')->toString());
        return \Illuminate\Support\Str::transliterate($canonicalIdentifier.'|'.$this->ip());
    }
}
