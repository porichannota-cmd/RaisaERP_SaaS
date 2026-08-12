<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaVisibility;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Tenant\ActiveTenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaAccessService
{
    public function __construct() {}

    /**
     * Authorize and stream a private media asset securely.
     */
    public function streamPrivateAsset(MediaAsset $asset): StreamedResponse
    {
        // 1. Enforce strict visibility semantics. Public assets shouldn't go through the private delivery controller typically,
        // but if they do, we allow it, though this is mainly for private assets.
        if ($asset->visibility === MediaVisibility::PRIVATE) {
            // 2. Enforce Tenant Isolation boundaries
            $currentTenantId = ActiveTenantContext::get();
            if ($asset->tenant_id !== $currentTenantId) {
                abort(403, 'MEDIA_ACCESS_DENIED: Asset does not belong to the active tenant context.');
            }

            // 3. IAM Authorization via Gate
            // This relies on the AuthorizationResolver we built in Wave 1A.
            // The Gate automatically triggers AuthorizationResolver check based on active context.
            if (! Gate::allows('media.view')) {
                abort(403, 'MEDIA_ACCESS_DENIED: Missing media.view permission for this tenant.');
            }
        }

        // 4. Validate Storage Presence
        $disk = Storage::disk($asset->storage_disk);
        if (! $disk->exists($asset->storage_path)) {
            abort(404, 'MEDIA_NOT_FOUND: The requested media asset is missing from storage.');
        }

        // 5. Stream the response
        return $disk->response($asset->storage_path, $asset->original_filename, [
            'Content-Type' => $asset->mime_type,
            'Content-Disposition' => 'inline; filename="'.basename($asset->original_filename).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Delete an asset from storage and registry securely.
     */
    public function secureDelete(MediaAsset $asset): void
    {
        $currentTenantId = ActiveTenantContext::get();

        if ($asset->tenant_id !== $currentTenantId) {
            abort(403, 'MEDIA_ACCESS_DENIED: Asset does not belong to the active tenant context.');
        }

        if (! Gate::allows('media.delete')) {
            abort(403, 'MEDIA_ACCESS_DENIED: Missing media.delete permission for this tenant.');
        }

        // Soft delete the registry record (the physical file remains until a purge job runs, adhering to safety semantics)
        $asset->delete();
    }
}
