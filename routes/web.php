<?php

use App\Http\Controllers\Api\Media\MediaDeliveryController;
use App\Http\Controllers\Api\Media\MediaUploadController;
use App\Http\Controllers\Api\Otp\OtpController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // Media
    Route::post('api/media', [MediaUploadController::class, 'store'])->name('api.media.store');
    Route::get('api/media/{id}', [MediaDeliveryController::class, 'show'])->name('api.media.show');
});

// OTP — public endpoints (unauthenticated; CSRF protected via web middleware)
Route::post('api/otp/send', [OtpController::class, 'send'])->name('api.otp.send');
Route::post('api/otp/verify', [OtpController::class, 'verify'])->name('api.otp.verify');
Route::post('api/otp/resend', [OtpController::class, 'resend'])->name('api.otp.resend');

// Health Check
Route::get('api/health', [\App\Http\Controllers\Api\HealthCheckController::class, 'index'])->name('api.health');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
