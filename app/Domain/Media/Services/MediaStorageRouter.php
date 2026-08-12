<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaVisibility;

class MediaStorageRouter
{
    /**
     * Generates a tenant-isolated storage path for the given asset.
     *
     * @param  string  $tenantId  The ULID of the tenant
     * @param  string  $ulid  The ULID of the media asset
     * @param  string  $extension  The safe file extension (e.g. 'webp', 'pdf')
     * @return string The server-owned storage path
     */
    public function generatePath(string $tenantId, string $ulid, string $extension, MediaVisibility $visibility): string
    {
        // Directory structure: tenants/{tenant_ulid}/media/{visibility}/{year}/{month}/{asset_ulid}.{ext}
        $year = date('Y');
        $month = date('m');

        $sanitizedExtension = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $extension));
        $visibilitySegment = $visibility->value;

        return sprintf(
            'tenants/%s/media/%s/%s/%s/%s.%s',
            $tenantId,
            $visibilitySegment,
            $year,
            $month,
            $ulid,
            $sanitizedExtension
        );
    }

    /**
     * Determine the correct Laravel storage disk based on visibility.
     */
    public function determineDisk(MediaVisibility $visibility): string
    {
        return $visibility === MediaVisibility::PUBLIC ? 'public' : 'local';
    }
}
