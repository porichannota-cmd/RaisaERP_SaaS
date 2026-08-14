<?php

use App\Http\Controllers\Business\BusinessSetupController;
use App\Http\Controllers\Business\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('business/setup', [BusinessSetupController::class, 'show'])->name('business.setup');
    Route::post('business/profile', [BusinessSetupController::class, 'saveProfile'])->name('business.profile.save');
    Route::post('business/address', [BusinessSetupController::class, 'saveAddress'])->name('business.address.save');
    Route::post('business/ready', [BusinessSetupController::class, 'markReady'])->name('business.ready');
    Route::post('business/provision', [BusinessSetupController::class, 'provision'])->name('business.provision');

    // Workspaces
    Route::middleware(['workspace.access'])->group(function () {
        Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('workspaces/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
        Route::post('workspaces/leave', [WorkspaceController::class, 'leave'])->name('workspaces.leave');
    });
});
