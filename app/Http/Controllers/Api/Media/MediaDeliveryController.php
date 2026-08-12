<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Media;

use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Services\MediaAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaDeliveryController extends Controller
{
    public function __construct(
        private readonly MediaAccessService $accessService
    ) {}

    public function show(Request $request, string $id): StreamedResponse
    {
        $asset = MediaAsset::findOrFail($id);

        return $this->accessService->streamPrivateAsset($asset);
    }
}
