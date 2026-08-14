<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->append(\App\Http\Middleware\CorrelationIdMiddleware::class);
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
        $middleware->alias([
            'tenant.active' => \App\Http\Middleware\ActiveTenantMiddleware::class,
            'account.access' => \App\Http\Middleware\EnforceAccountAccess::class,
            'workspace.access' => \App\Http\Middleware\EnforceWorkspaceAccess::class,
        ]);
    })
        ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface 
                    ? $e->getStatusCode() 
                    : 500;
                    
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = $e->status;
                    return response()->json([
                        'error' => 'Validation Failed',
                        'message' => $e->getMessage(),
                        'errors' => $e->errors(),
                        'correlation_id' => $request->header('X-Correlation-ID'),
                    ], $status);
                }

                return response()->json([
                    'error' => class_basename($e),
                    'message' => config('app.debug') ? $e->getMessage() : 'Server Error',
                    'correlation_id' => $request->header('X-Correlation-ID'),
                ], $status);
            }
        });
    })->create();

