<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Media;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Services\MediaUploadService;
use App\Domain\Tenant\ActiveTenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MediaUploadController extends Controller
{
    public function __construct(
        private readonly MediaUploadService $uploadService
    ) {}

    public function store(MediaUploadRequest $request): JsonResponse
    {
        $tenantId = ActiveTenantContext::get();

        // 1. Authorize using IAM
        Gate::authorize('media.upload');

        // 2. Ingest Media
        $file = $request->file('file');
        $kind = MediaKind::from($request->input('kind'));

        $asset = $this->uploadService->ingest($file, $kind);

        return response()->json([
            'id' => $asset->id,
            'original_filename' => $asset->original_filename,
            'media_kind' => $asset->media_kind->value,
            'visibility' => $asset->visibility->value,
            'processing_status' => $asset->processing_status->value,
            'created_at' => $asset->created_at->toIso8601String(),
        ], 201);
    }
}
