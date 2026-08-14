<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AccountReviewController;

Route::middleware(['auth', 'verified'])->group(function () {
    
    // Self-service submission
    Route::post('/account/approvals', [AccountReviewController::class, 'store'])->name('account.approvals.store');
    
    // Platform Reviewer Admin Routes
    Route::middleware([\App\Http\Middleware\PlatformReviewerMiddleware::class])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('/approvals', [AccountReviewController::class, 'index'])->name('approvals.index');
            Route::post('/approvals/{reviewRequest}/approve', [AccountReviewController::class, 'approve'])->name('approvals.approve');
            Route::post('/approvals/{reviewRequest}/reject', [AccountReviewController::class, 'reject'])->name('approvals.reject');
        });
});
