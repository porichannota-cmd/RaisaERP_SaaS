<?php

namespace App\Domain\IAM\Enums;

enum RoleType: string
{
    case PLATFORM_SYSTEM = 'platform_system';
    case TENANT_SYSTEM = 'tenant_system';
    case TENANT_CUSTOM = 'tenant_custom';
}
