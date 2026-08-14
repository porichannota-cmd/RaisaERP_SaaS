<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Profile\AddBankAccountRequest;
use App\Http\Requests\Profile\UpdateBankAccountRequest;
use App\Domain\Profile\Services\UserBankAccountService;

class BankAccountController extends Controller
{
    public function __construct(private readonly UserBankAccountService $service) {}

    public function store(AddBankAccountRequest $request)
    {
        $this->service->addAccount($request->user(), $request->validated());
        return back()->with('status', 'bank-account-added');
    }

    public function update(UpdateBankAccountRequest $request, string $id)
    {
        $this->service->updateAccount($request->user(), $id, $request->validated());
        return back()->with('status', 'bank-account-updated');
    }

    public function destroy(Request $request, string $id)
    {
        $this->service->deleteAccount($request->user(), $id);
        return back()->with('status', 'bank-account-deleted');
    }
}
