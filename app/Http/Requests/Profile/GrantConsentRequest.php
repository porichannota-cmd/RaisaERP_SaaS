<?php

namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GrantConsentRequest extends FormRequest
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
            'consent_type' => ['required', 'string', 'in:TERMS_OF_SERVICE,PRIVACY_POLICY,MARKETING'],
            'document_version' => ['required', 'string', 'max:255'],
            'document_hash' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'ip_fingerprint' => ['nullable', 'string', 'max:255'],
        ];
    }
}
