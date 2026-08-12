<?php

declare(strict_types=1);

namespace App\Domain\Media\Models;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaProcessingStatus;
use App\Domain\Media\Enums\MediaSecurityStatus;
use App\Domain\Media\Enums\MediaVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaAsset extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'media_assets';

    protected $fillable = [
        'tenant_id',
        'uploaded_by',
        'original_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'media_kind',
        'visibility',
        'processing_status',
        'security_status',
        'metadata',
    ];

    protected $casts = [
        'media_kind' => MediaKind::class,
        'visibility' => MediaVisibility::class,
        'processing_status' => MediaProcessingStatus::class,
        'security_status' => MediaSecurityStatus::class,
        'metadata' => 'array',
        'size_bytes' => 'integer',
    ];
}
