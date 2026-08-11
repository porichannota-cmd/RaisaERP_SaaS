<?php

namespace App\Domain\IAM\Enums;

enum PositionAssignmentStatus: string
{
    case ACTIVE = 'active';
    case ENDED = 'ended';
    case CANCELLED = 'cancelled';
}
