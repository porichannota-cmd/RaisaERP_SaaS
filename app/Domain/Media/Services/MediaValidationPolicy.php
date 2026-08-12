<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaVisibility;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MediaValidationPolicy
{
    /**
     * Maps MediaKind to allowed extensions, mimes, max size (bytes).
     */
    private const POLICY = [
        MediaKind::IMAGE->value => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size' => 10 * 1024 * 1024, // 10MB
            'default_visibility' => MediaVisibility::PUBLIC,
        ],
        MediaKind::IDENTITY_DOCUMENT->value => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            'max_size' => 5 * 1024 * 1024, // 5MB
            'default_visibility' => MediaVisibility::PRIVATE, // Strictly private
        ],
        MediaKind::DOCUMENT->value => [
            'extensions' => ['pdf'],
            'mimes' => ['application/pdf'],
            'max_size' => 20 * 1024 * 1024, // 20MB
            'default_visibility' => MediaVisibility::PRIVATE,
        ],
        MediaKind::PRODUCT_MEDIA->value => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size' => 15 * 1024 * 1024, // 15MB
            'default_visibility' => MediaVisibility::PUBLIC,
        ],
    ];

    /**
     * Validates the uploaded file against the rules defined for the given media kind.
     * Throws ValidationException if validation fails.
     */
    public function validate(UploadedFile $file, MediaKind $kind): void
    {
        $policy = self::POLICY[$kind->value] ?? null;

        if (! $policy) {
            throw ValidationException::withMessages([
                'file' => ['MEDIA_TYPE_NOT_ALLOWED', 'Unsupported media kind for upload.'],
            ]);
        }

        // 1. Size Validation
        if ($file->getSize() > $policy['max_size']) {
            throw ValidationException::withMessages([
                'file' => ['MEDIA_FILE_TOO_LARGE', 'File exceeds the maximum allowed size for this media kind.'],
            ]);
        }

        // 2. Extension Validation (client-provided, but we check it first as a fast-fail)
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $policy['extensions'], true)) {
            throw ValidationException::withMessages([
                'file' => ['MEDIA_EXTENSION_NOT_ALLOWED', 'File extension is not allowed.'],
            ]);
        }

        // 3. MIME/Magic Byte Validation (server-side detection)
        $mime = $file->getMimeType();
        if (! in_array($mime, $policy['mimes'], true)) {
            throw ValidationException::withMessages([
                'file' => ['MEDIA_MIME_MISMATCH', 'Detected file content does not match allowed types.'],
            ]);
        }

        // 4. Dimension Protection (for images)
        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($file->getRealPath());
            if ($dimensions === false) {
                throw ValidationException::withMessages([
                    'file' => ['MEDIA_CORRUPT_IMAGE', 'The image file appears to be corrupt or unreadable.'],
                ]);
            }

            $width = $dimensions[0];
            $height = $dimensions[1];

            // Protect against decompression bombs (e.g. max 10000x10000)
            if ($width > 10000 || $height > 10000) {
                throw ValidationException::withMessages([
                    'file' => ['MEDIA_DIMENSIONS_EXCEEDED', 'Image dimensions exceed the safe maximum limit.'],
                ]);
            }
        }
    }

    public function getDefaultVisibility(MediaKind $kind): MediaVisibility
    {
        $policy = self::POLICY[$kind->value] ?? null;

        return $policy['default_visibility'] ?? MediaVisibility::PRIVATE;
    }
}
