<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Registration;

use App\Domain\Registration\Enums\RegistrationDocumentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRegistrationIdentityDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Authorization (token ownership) is handled in the controller/service.
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
            'kind' => [
                'required',
                'string',
                new Enum(RegistrationDocumentKind::class),
                // Exclude passport for Wave 2C public API
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === RegistrationDocumentKind::PASSPORT->value) {
                        $fail('The selected document kind is not accepted for this registration channel.');
                    }
                },
            ],
            // We do a basic size check here, but the service enforces exact MIME/size per policy.
            'file' => [
                'required',
                'file',
                'image',       // Strictly images only for Wave 2C
                'mimes:jpeg,jpg,png,webp',
                'max:10240',   // 10MB max, further constrained by MediaValidationPolicy if needed
            ],
        ];
    }
}
