<?php

use App\Http\Controllers\Profile\AddressController;
use App\Http\Controllers\Profile\BankAccountController;
use App\Http\Controllers\Profile\ConsentController;
use App\Http\Controllers\Profile\ContactController;
use App\Http\Controllers\Profile\MfsAccountController;
use App\Http\Controllers\Profile\OnboardingController;
use App\Http\Controllers\Profile\PersonalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', \App\Http\Middleware\EnforceAccountAccess::class])->group(function () {
    Route::get('profile', [OnboardingController::class, 'index'])->name('profile');

    Route::patch('profile/personal', [PersonalController::class, 'update'])->name('profile.personal.update');
    Route::patch('profile/contact', [ContactController::class, 'update'])->name('profile.contact.update');

    Route::post('profile/addresses', [AddressController::class, 'upsert'])->name('profile.addresses.upsert');
    Route::post('profile/addresses/copy-present', [AddressController::class, 'copyPresent'])->name('profile.addresses.copy-present');
    Route::delete('profile/addresses/{id}', [AddressController::class, 'destroy'])->name('profile.addresses.destroy');

    Route::post('profile/bank-accounts', [BankAccountController::class, 'store'])->name('profile.bank-accounts.store');
    Route::patch('profile/bank-accounts/{id}', [BankAccountController::class, 'update'])->name('profile.bank-accounts.update');
    Route::delete('profile/bank-accounts/{id}', [BankAccountController::class, 'destroy'])->name('profile.bank-accounts.destroy');

    Route::post('profile/mfs-accounts', [MfsAccountController::class, 'store'])->name('profile.mfs-accounts.store');
    Route::patch('profile/mfs-accounts/{id}', [MfsAccountController::class, 'update'])->name('profile.mfs-accounts.update');
    Route::delete('profile/mfs-accounts/{id}', [MfsAccountController::class, 'destroy'])->name('profile.mfs-accounts.destroy');

    Route::post('profile/consents', [ConsentController::class, 'grant'])->name('profile.consents.grant');
    Route::delete('profile/consents/{type}', [ConsentController::class, 'revoke'])->name('profile.consents.revoke');
});
