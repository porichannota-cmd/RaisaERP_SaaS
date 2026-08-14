<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Account\Services\PlatformAccountReviewAuthorizer;

class PlatformReviewerMiddleware
{
    public function __construct(private readonly PlatformAccountReviewAuthorizer $authorizer) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$this->authorizer->mayViewQueue($request->user())) {
            abort(403, 'Unauthorized Platform Reviewer.');
        }

        return $next($request);
    }
}
