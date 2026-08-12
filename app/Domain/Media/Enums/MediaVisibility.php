<?php

declare(strict_types=1);

namespace App\Domain\Media\Enums;

enum MediaVisibility: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
}
