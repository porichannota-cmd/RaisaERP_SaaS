<?php

declare(strict_types=1);

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistrationAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:50'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
