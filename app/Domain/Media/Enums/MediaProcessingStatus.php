<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

enum MediaProcessingStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case FAILED = 'failed';
    case PENDING_ATTACHMENT = 'pending_attachment';
}
