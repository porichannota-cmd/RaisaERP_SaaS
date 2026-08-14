<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Profile\UpdatePersonalProfileRequest;
use App\Domain\Profile\Services\UserProfileService;

class PersonalController extends Controller
{
    public function __construct(private readonly UserProfileService $service) {}

    public function update(UpdatePersonalProfileRequest $request)
    {
        $this->service->updatePersonalProfile($request->user(), $request->validated());
        return back()->with('status', 'personal-profile-updated');
    }
}
