<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Profile\UpdateContactProfileRequest;
use App\Domain\Profile\Services\UserContactService;

class ContactController extends Controller
{
    public function __construct(private readonly UserContactService $service) {}

    public function update(UpdateContactProfileRequest $request)
    {
        $this->service->updateContactDetails($request->user(), $request->validated());
        return back()->with('status', 'contact-profile-updated');
    }
}
