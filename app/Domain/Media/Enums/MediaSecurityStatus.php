<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

enum MediaSecurityStatus: string
{
    case PENDING = 'pending';
    case CLEAN = 'clean';
    case INFECTED = 'infected';
    case QUARANTINED = 'quarantined';
    case NOT_AVAILABLE = 'not_available';
}
