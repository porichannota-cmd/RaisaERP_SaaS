<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Account\Services\AccountReviewService;
use App\Http\Controllers\Controller;
use App\Models\AccountReviewRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountReviewController extends Controller
{
    public function __construct(private readonly AccountReviewService $reviewService)
    {
    }

    public function index(): Response
    {
        $requests = AccountReviewRequest::with(['user' => function($q) {
            $q->select('id', 'name', 'email', 'mobile_number', 'account_status');
        }])
            ->where('status', 'PENDING')
            ->latest('submitted_at')
            ->paginate(20)
            ->through(fn($request) => [
                'id' => $request->id,
                'user' => [
                    'id' => $request->user->id,
                    'name' => $request->user->name,
                    'email' => $request->user->email, // Could be masked here if PA desired, but keeping to standard
                    'account_status' => $request->user->account_status,
                ],
                'status' => $request->status,
                'submitted_at' => $request->submitted_at,
            ]);

        return Inertia::render('admin/approvals/index', [
            'requests' => $requests,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        $this->reviewService->requestReview($user);

        return back()->with('status', 'Account submitted for review.');
    }

    public function approve(Request $request, AccountReviewRequest $reviewRequest)
    {
        $this->reviewService->approve($reviewRequest, $request->user());

        return back()->with('status', 'Account approved successfully.');
    }

    public function reject(Request $request, AccountReviewRequest $reviewRequest)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $this->reviewService->reject($reviewRequest, $request->user(), $request->input('reason'));

        return back()->with('status', 'Account rejected successfully.');
    }
}
