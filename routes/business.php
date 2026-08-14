<?php

use App\Http\Controllers\Business\BusinessSetupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('business/setup', [BusinessSetupController::class, 'show'])->name('business.setup');
    Route::post('business/profile', [BusinessSetupController::class, 'saveProfile'])->name('business.profile.save');
    Route::post('business/address', [BusinessSetupController::class, 'saveAddress'])->name('business.address.save');
    Route::post('business/ready', [BusinessSetupController::class, 'markReady'])->name('business.ready');
    Route::post('business/provision', [BusinessSetupController::class, 'provision'])->name('business.provision');
});
