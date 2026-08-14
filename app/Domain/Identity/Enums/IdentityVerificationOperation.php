<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum IdentityVerificationOperation: string
{
    case EXTRACT = 'extract';
    case VERIFY = 'verify';
}
