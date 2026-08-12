<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     * Wave 2B PA Decision: GET /register -> transition to Wave 2 registration entry without redirect loops.
     */
    public function create(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        // If a frontend SPA path for new registration exists, Inertia::render it.
        // For now, we render a placeholder or the same auth/register but ensure it directs to API.
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     * Wave 2B PA Decision: POST /register -> 410 Gone
     */
    public function store(Request $request)
    {
        abort(410, 'Legacy registration is no longer supported. Please use the mobile-first registration API.');
    }
}
