<?php

declare(strict_types=1);

namespace App\Http\Controllers\Business;

use App\Domain\IAM\Enums\MembershipStatus;
use App\Domain\IAM\Models\TenantMembership;
use App\Domain\Tenant\ActiveTenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = ActiveTenantContext::get();
        $user = $request->user();

        $membership = TenantMembership::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::ACTIVE)
            ->with(['tenant', 'membershipRoles.role'])
            ->firstOrFail();

        $roles = $membership->membershipRoles->whereNull('revoked_at')->pluck('role.name');

        $accessLevel = $roles->isNotEmpty()
            ? $roles->implode(', ')
            : 'Member';

        $payload = [
            'workspace' => [
                'id' => $membership->tenant->id,
                'name' => $membership->tenant->name,
                'status' => $membership->tenant->status ?? 'ACTIVE',
            ],
            'membership' => [
                'status' => $membership->status->value ?? 'active',
                'access_level' => $accessLevel,
            ],
            'account' => [
                'name' => $user->name,
                'status' => $user->account_status->value ?? 'active',
            ]
        ];

        return Inertia::render('dashboard', [
            'dashboardData' => $payload
        ]);
    }
}
