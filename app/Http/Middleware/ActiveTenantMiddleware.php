<?php

namespace App\Http\Middleware;

use App\Domain\Tenant\Services\WorkspaceContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveTenantMiddleware
{
    public function __construct(
        private readonly WorkspaceContextService $workspaceContextService
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Active workspace will be resolved, revalidated, and context established.
        // If invalid, the service automatically clears the session.
        $tenant = $this->workspaceContextService->resolveActiveWorkspace($user);

        if (! $tenant) {
            return redirect()->route('workspaces.index');
        }

        \Inertia\Inertia::share('activeWorkspace', [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'status' => $tenant->status,
        ]);

        try {
            return $next($request);
        } finally {
            \App\Domain\Tenant\ActiveTenantContext::clear();
        }
    }
}
