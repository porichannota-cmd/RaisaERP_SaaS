<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum IdentityVerificationStatus: string
{
    case NOT_STARTED = 'NOT_STARTED';
    case EXTRACTION_PENDING = 'EXTRACTION_PENDING';
    case EXTRACTED = 'EXTRACTED';
    case VERIFICATION_PENDING = 'VERIFICATION_PENDING';
    case VERIFIED = 'VERIFIED';
    case FAILED = 'FAILED';
    case MANUAL_REVIEW_REQUIRED = 'MANUAL_REVIEW_REQUIRED';
    case REJECTED = 'REJECTED';
}
