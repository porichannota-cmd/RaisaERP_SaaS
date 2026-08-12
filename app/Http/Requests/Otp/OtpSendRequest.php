<?php

namespace App\Http\Requests\Otp;

use App\Domain\Communication\Enums\OtpChannel;
use App\Domain\Communication\Enums\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OtpSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Unauthenticated for public purposes; purpose-level check in controller
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::enum(OtpChannel::class)],
            'destination' => ['required', 'string', 'max:320'],
            'purpose' => ['required', 'string', Rule::enum(OtpPurpose::class)],
        ];
    }
}
