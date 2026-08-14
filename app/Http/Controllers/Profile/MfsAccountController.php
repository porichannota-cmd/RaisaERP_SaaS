<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Profile\AddMfsAccountRequest;
use App\Http\Requests\Profile\UpdateMfsAccountRequest;
use App\Domain\Profile\Services\UserMfsAccountService;

class MfsAccountController extends Controller
{
    public function __construct(private readonly UserMfsAccountService $service) {}

    public function store(AddMfsAccountRequest $request)
    {
        $this->service->addAccount($request->user(), $request->validated());
        return back()->with('status', 'mfs-account-added');
    }

    public function update(UpdateMfsAccountRequest $request, string $id)
    {
        $this->service->updateAccount($request->user(), $id, $request->validated());
        return back()->with('status', 'mfs-account-updated');
    }

    public function destroy(Request $request, string $id)
    {
        $this->service->deleteAccount($request->user(), $id);
        return back()->with('status', 'mfs-account-deleted');
    }
}
