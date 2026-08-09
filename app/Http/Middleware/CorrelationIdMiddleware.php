<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = Str::uuid()->toString();
        $correlationId = $request->header('X-Correlation-ID', $requestId);

        $request->headers->set('X-Request-ID', $requestId);
        $request->headers->set('X-Correlation-ID', $correlationId);

        // Append to all logs for this request
        Log::shareContext([
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
        ]);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
