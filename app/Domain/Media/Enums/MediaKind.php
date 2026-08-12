<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

enum MediaKind: string
{
    case IMAGE = 'image';
    case DOCUMENT = 'document';
    case IDENTITY_DOCUMENT = 'identity_document';
    case PRODUCT_MEDIA = 'product_media';
    case BANNER = 'banner';
    case PDF = 'pdf';
    case SIGNATURE = 'signature';
    case OTHER = 'other';
}
