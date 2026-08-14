<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum IdentityExtractionProviderStatus: string
{
    case SUCCESS = 'SUCCESS';
    case NOT_AVAILABLE = 'NOT_AVAILABLE';
    case INVALID_DOCUMENT = 'INVALID_DOCUMENT';
    case LOW_CONFIDENCE = 'LOW_CONFIDENCE';
    case PROVIDER_ERROR = 'PROVIDER_ERROR';
}
