<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum IdentityVerificationProviderStatus: string
{
    case VERIFIED = 'VERIFIED';
    case NOT_VERIFIED = 'NOT_VERIFIED';
    case NOT_AVAILABLE = 'NOT_AVAILABLE';
    case MANUAL_REVIEW_REQUIRED = 'MANUAL_REVIEW_REQUIRED';
    case PROVIDER_ERROR = 'PROVIDER_ERROR';
}
