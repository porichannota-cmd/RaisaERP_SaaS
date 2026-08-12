<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Contracts\ImageOptimizerInterface;
use App\Domain\Media\Contracts\MalwareScannerInterface;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaProcessingStatus;
use App\Domain\Media\Enums\MediaSecurityStatus;
use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Tenant\ActiveTenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    public function __construct(
        private readonly MediaValidationPolicy $validationPolicy,
        private readonly MediaStorageRouter $storageRouter,
        private readonly MalwareScannerInterface $scanner,
        private readonly ImageOptimizerInterface $imageOptimizer
    ) {}

    /**
     * Ingests a new media file into the gateway.
     */
    public function ingest(UploadedFile $file, MediaKind $kind, ?MediaVisibility $requestedVisibility = null): MediaAsset
    {
        $tenantId = ActiveTenantContext::get();
        $userId = auth()->id();

        // 1. Initial Preflight Validation
        $this->validationPolicy->validate($file, $kind);

        // 2. Determine Visibility
        $visibility = $requestedVisibility ?? $this->validationPolicy->getDefaultVisibility($kind);

        // 3. Security Inspection
        $securityStatus = $this->scanner->scan($file);

        // If infected, we do not finalize normally.
        if ($securityStatus === MediaSecurityStatus::INFECTED || $securityStatus === MediaSecurityStatus::QUARANTINED) {
            abort(422, 'MEDIA_SECURITY_REJECTED: The file failed security scanning.');
        }

        // Identity documents strictly require a clean scan if a scanner is mandated.
        // For Wave 1B default NullMalwareScanner, NOT_AVAILABLE is permitted to pass if policy allows.

        // 4. Determine Storage Routing
        $ulid = (string) Str::ulid();
        $disk = $this->storageRouter->determineDisk($visibility);

        $originalExt = strtolower($file->getClientOriginalExtension());

        $metadata = [];
        $processingStatus = MediaProcessingStatus::READY;
        $finalExtension = $originalExt;
        $finalMime = $file->getMimeType();
        $finalSizeBytes = $file->getSize();

        // 5. Image Normalization & Metadata Extraction (If applicable)
        // Note: For Wave 1B, we process images synchronously for simplicity of the foundation.
        // Future iterations may move this to async queues.
        $tmpOptimizedPath = null;
        if (str_starts_with($finalMime, 'image/') && $originalExt !== 'svg') {
            $tmpOptimizedPath = tempnam(sys_get_temp_dir(), 'img_opt_');
            $optimizationResult = $this->imageOptimizer->optimize($file->getRealPath(), $tmpOptimizedPath);

            $metadata['width'] = $optimizationResult['width'];
            $metadata['height'] = $optimizationResult['height'];
            $metadata['exif_stripped'] = true;

            $finalExtension = $optimizationResult['extension'];
            $finalMime = $optimizationResult['mime'];
            $finalSizeBytes = $optimizationResult['size'];
        }

        $storagePath = $this->storageRouter->generatePath($tenantId, $ulid, $finalExtension, $visibility);

        // Calculate Checksum of the FINAL bytes that will be stored
        $sourceForChecksum = $tmpOptimizedPath ? $tmpOptimizedPath : $file->getRealPath();
        $checksum = hash_file('sha256', $sourceForChecksum);

        // 6. Atomic Finalization
        return DB::transaction(function () use (
            $tenantId, $userId, $ulid, $file, $disk, $storagePath, $finalMime, $finalExtension,
            $finalSizeBytes, $checksum, $kind, $visibility, $processingStatus, $securityStatus, $metadata,
            $tmpOptimizedPath, $sourceForChecksum
        ) {
            // Write to storage
            $stream = fopen($sourceForChecksum, 'r+');
            $stored = Storage::disk($disk)->put($storagePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $stored) {
                throw new \RuntimeException('MEDIA_PROCESSING_FAILED: Storage write failed.');
            }

            // Cleanup temp file if we created one
            if ($tmpOptimizedPath && file_exists($tmpOptimizedPath)) {
                @unlink($tmpOptimizedPath);
            }

            // Persist Registry Record
            // Note: Since this might be an Avatar or NID, we default to PENDING_ATTACHMENT if no parent is provided yet.
            // But we will allow the controller to override it. Here we use READY for a finalized standalone upload.

            try {
                return MediaAsset::create([
                    'id' => $ulid,
                    'tenant_id' => $tenantId,
                    'uploaded_by' => $userId,
                    'original_filename' => substr(preg_replace('/[^a-zA-Z0-9_.-]/', '', $file->getClientOriginalName()), 0, 255),
                    'storage_disk' => $disk,
                    'storage_path' => $storagePath,
                    'mime_type' => $finalMime,
                    'extension' => $finalExtension,
                    'size_bytes' => $finalSizeBytes,
                    'checksum_sha256' => $checksum,
                    'media_kind' => $kind,
                    'visibility' => $visibility,
                    'processing_status' => $processingStatus,
                    'security_status' => $securityStatus,
                    'metadata' => $metadata,
                ]);
            } catch (\Exception $e) {
                // Compensating action: attempt to remove the orphan file if DB persistence fails
                try {
                    Storage::disk($disk)->delete($storagePath);
                } catch (\Exception $cleanupError) {
                    Log::error('MEDIA_ORPHAN_CLEANUP_FAILED', [
                        'disk' => $disk,
                        'path' => $storagePath,
                        'error' => $cleanupError->getMessage(),
                    ]);
                }
                throw $e;
            }
        });
    }
}
