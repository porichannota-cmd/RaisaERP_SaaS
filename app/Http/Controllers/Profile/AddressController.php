<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Profile\UpsertAddressRequest;
use App\Domain\Profile\Services\UserAddressService;

class AddressController extends Controller
{
    public function __construct(private readonly UserAddressService $service) {}

    public function upsert(UpsertAddressRequest $request)
    {
        $this->service->upsertAddress($request->user(), $request->type, $request->validated());
        return back()->with('status', 'address-updated');
    }

    public function copyPresent(Request $request)
    {
        $this->service->copyPresentToPermanent($request->user());
        return back()->with('status', 'address-copied');
    }

    public function destroy(Request $request, string $id)
    {
        $this->service->deleteAddress($request->user(), $id);
        return back()->with('status', 'address-deleted');
    }
}
