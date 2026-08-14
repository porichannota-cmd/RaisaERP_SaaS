<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Profile\GrantConsentRequest;
use App\Domain\Profile\Services\UserConsentService;

class ConsentController extends Controller
{
    public function __construct(private readonly UserConsentService $service) {}

    public function grant(GrantConsentRequest $request)
    {
        $this->service->grantConsent($request->user(), $request->validated());
        return back()->with('status', 'consent-granted');
    }

    public function revoke(Request $request, string $type)
    {
        $this->service->revokeConsent($request->user(), $type);
        return back()->with('status', 'consent-revoked');
    }
}
