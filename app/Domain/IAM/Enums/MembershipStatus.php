<?php

namespace App\Domain\IAM\Enums;

enum MembershipStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TERMINATED = 'terminated';
}
