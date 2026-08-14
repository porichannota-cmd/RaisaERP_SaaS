<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddBankAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'routing_number' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
