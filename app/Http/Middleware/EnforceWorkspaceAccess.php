<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Registration\Policies\AccountAccessPolicy;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class EnforceWorkspaceAccess
{
    public function __construct(private readonly AccountAccessPolicy $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            try {
                $this->policy->ensureCanAccessWorkspace(Auth::user());
            } catch (ValidationException $e) {
                // Return 403 Forbidden to clearly indicate operational boundaries are exceeded,
                // without necessarily logging out the user (unlike EnforceAccountAccess).
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['message' => $e->getMessage()], 403);
                }
                abort(403, $e->getMessage());
            }
        }

        return $next($request);
    }
}
