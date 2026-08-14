<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Registration\Policies\AccountAccessPolicy;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class EnforceAccountAccess
{
    public function __construct(private readonly AccountAccessPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            try {
                $this->policy->ensureCanAuthenticate(Auth::user());
            } catch (ValidationException $e) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors($e->errors());
            }
        }

        return $next($request);
    }
}
