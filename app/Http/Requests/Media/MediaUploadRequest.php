<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use App\Domain\Media\Enums\MediaKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ActiveTenantContext handles overall tenant scope, but IAM gate should check upload perm.
        // We defer to the controller to use Gate::authorize('media.upload') properly for the active tenant.
        return true;
    }

    public function rules(): array
    {
        return [
            // The file itself. Max size is primarily enforced by MediaValidationPolicy,
            // but we add a basic upper bound here (e.g., 50MB) to prevent large payload attacks before policy check.
            'file' => ['required', 'file', 'max:51200'],
            'kind' => ['required', new Enum(MediaKind::class)],
        ];
    }
}
