<?php

namespace App\Http\Controllers\Business;

use App\Domain\Tenant\Services\WorkspaceContextService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceContextService $workspaceService
    ) {
    }

    public function index(Request $request)
    {
        $workspaces = $this->workspaceService->listAvailableWorkspaces($request->user());

        return Inertia::render('Business/Workspaces/Index', [
            'workspaces' => $workspaces->map(fn ($tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status,
            ]),
        ]);
    }

    public function switch(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string', 'size:26'],
        ]);

        $success = $this->workspaceService->switchWorkspace($request->user(), $validated['tenant_id']);

        if (! $success) {
            abort(403, 'Unauthorized workspace access.');
        }

        return redirect()->route('dashboard');
    }

    public function leave()
    {
        $this->workspaceService->clearWorkspace();
        return redirect()->route('workspaces.index');
    }
}
