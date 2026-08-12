<?php

declare(strict_types=1);

namespace App\Domain\Registration\Services;

use App\Domain\Media\Contracts\ImageOptimizerInterface;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Services\MediaValidationPolicy;
use App\Domain\Registration\Enums\RegistrationDocumentKind;
use App\Domain\Registration\Enums\RegistrationDocumentStatus;
use App\Models\RegistrationIdentityDocument;
use App\Models\RegistrationSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegistrationIdentityDocumentService
{
    private const MAX_PIXELS = 25000000; // 25 Megapixels limit to prevent decompression bombs

    public function __construct(
        private readonly MediaValidationPolicy $validationPolicy,
        private readonly ImageOptimizerInterface $imageOptimizer
    ) {}

    public function upload(RegistrationSession $session, UploadedFile $file, RegistrationDocumentKind $kind): RegistrationIdentityDocument
    {
        // 1. Authorization: Session must be in a state that allows identity upload.
        if (! $session->isVerified() || $session->isExpired() || $session->isConsumed()) {
            throw ValidationException::withMessages([
                'session' => ['REGISTRATION_SESSION_INVALID', 'The registration session is not in a valid state for uploading identity documents.'],
            ]);
        }

        // 2. Map RegistrationDocumentKind to MediaKind for base validation
        $mediaKind = match ($kind) {
            RegistrationDocumentKind::PROFILE_PHOTO => MediaKind::IMAGE,
            RegistrationDocumentKind::NID_FRONT,
            RegistrationDocumentKind::NID_BACK,
            RegistrationDocumentKind::PASSPORT => MediaKind::IDENTITY_DOCUMENT,
        };

        // 3. Preflight Validation
        $this->validationPolicy->validate($file, $mediaKind);

        // 4. Strict Image Checks (Reject disguised files, enforce max pixels)
        $mime = $file->getMimeType();
        if (! str_starts_with($mime, 'image/')) {
            throw ValidationException::withMessages([
                'file' => ['INVALID_FILE_TYPE', 'Only image files are allowed.'],
            ]);
        }

        $dimensions = @getimagesize($file->getRealPath());
        if ($dimensions === false) {
            throw ValidationException::withMessages([
                'file' => ['MEDIA_CORRUPT_IMAGE', 'The image file appears to be corrupt or unreadable.'],
            ]);
        }

        $width = $dimensions[0];
        $height = $dimensions[1];

        if (($width * $height) > self::MAX_PIXELS) {
            throw ValidationException::withMessages([
                'file' => ['MEDIA_DIMENSIONS_EXCEEDED', 'Image dimensions exceed the safe maximum pixel limit.'],
            ]);
        }

        // 5. Optimization & Normalization
        $tmpOptimizedPath = tempnam(sys_get_temp_dir(), 'reg_doc_');

        try {
            $optimizationResult = $this->imageOptimizer->optimize($file->getRealPath(), $tmpOptimizedPath);

            $finalExtension = $optimizationResult['extension'];
            $finalMime = $optimizationResult['mime'];
            $finalSizeBytes = $optimizationResult['size'];
            $finalWidth = $optimizationResult['width'];
            $finalHeight = $optimizationResult['height'];

            $sourceForChecksum = $tmpOptimizedPath;
            $checksum = hash_file('sha256', $sourceForChecksum);

            // 6. Define Storage Path (Isolated pre-user namespace)
            $ulid = (string) Str::ulid();
            // private disk for identity docs
            $disk = 'private';
            $storagePath = sprintf('registration/%s/%s.%s', $session->id, $ulid, $finalExtension);

            // 7. Atomic Storage and DB replacement
            return DB::transaction(function () use (
                $session, $kind, $ulid, $disk, $storagePath, $file, $finalMime,
                $finalSizeBytes, $checksum, $finalWidth, $finalHeight, $sourceForChecksum
            ) {
                // Find existing active document of the same kind
                $existingDoc = RegistrationIdentityDocument::where('registration_session_id', $session->id)
                    ->where('kind', $kind->value)
                    ->first();

                // Write new file to storage
                $stream = fopen($sourceForChecksum, 'r+');
                $stored = Storage::disk($disk)->put($storagePath, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $stored) {
                    throw new \RuntimeException('REGISTRATION_DOCUMENT_STORAGE_FAILED: Storage write failed.');
                }

                try {
                    // Delete existing DB record (Replacement semantics)
                    if ($existingDoc) {
                        $existingDoc->delete();
                    }

                    // Create new record
                    $newDoc = RegistrationIdentityDocument::create([
                        'id' => $ulid,
                        'registration_session_id' => $session->id,
                        'kind' => $kind,
                        'storage_disk' => $disk,
                        'storage_path' => $storagePath,
                        'original_filename_safe' => substr(preg_replace('/[^a-zA-Z0-9_.-]/', '', $file->getClientOriginalName()), 0, 255),
                        'detected_mime' => $finalMime,
                        'size_bytes' => $finalSizeBytes,
                        'checksum_sha256' => $checksum,
                        'width' => $finalWidth,
                        'height' => $finalHeight,
                        'status' => RegistrationDocumentStatus::VALIDATED,
                        'expires_at' => $session->expires_at,
                        'metadata' => ['exif_stripped' => true],
                    ]);

                    // Physically remove the old file only AFTER DB transaction successfully commits
                    if ($existingDoc) {
                        DB::afterCommit(function () use ($existingDoc) {
                            Storage::disk($existingDoc->storage_disk)->delete($existingDoc->storage_path);
                        });
                    }

                    return $newDoc;
                } catch (\Exception $e) {
                    // Compensation: DB failed, delete the newly uploaded file
                    try {
                        Storage::disk($disk)->delete($storagePath);
                    } catch (\Exception $cleanupError) {
                        Log::error('REGISTRATION_DOCUMENT_ORPHAN_CLEANUP_FAILED', [
                            'disk' => $disk,
                            'path' => $storagePath,
                            'error' => $cleanupError->getMessage(),
                        ]);
                    }
                    throw $e;
                }
            });
        } finally {
            if ($tmpOptimizedPath && file_exists($tmpOptimizedPath)) {
                @unlink($tmpOptimizedPath);
            }
        }
    }

    public function delete(RegistrationSession $session, string $documentId): bool
    {
        $document = RegistrationIdentityDocument::where('registration_session_id', $session->id)
            ->where('id', $documentId)
            ->firstOrFail();

        return DB::transaction(function () use ($document) {
            $deleted = $document->delete();
            if ($deleted) {
                Storage::disk($document->storage_disk)->delete($document->storage_path);
            }

            return $deleted;
        });
    }
}
