<?php

namespace App\Http\Requests\Otp;

use App\Domain\Communication\Enums\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'otp_id' => ['required', 'string', 'size:26'],
            'code' => ['required', 'string', 'min:4', 'max:10'],
            'purpose' => ['required', 'string', Rule::enum(OtpPurpose::class)],
        ];
    }
}
