<?php

declare(strict_types=1);

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class RegistrationOtpVerifyRequest extends FormRequest
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
            'code' => ['required', 'string', 'min:4', 'max:10'],
        ];
    }
}
