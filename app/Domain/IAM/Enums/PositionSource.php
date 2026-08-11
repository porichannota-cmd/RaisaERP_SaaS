<?php

namespace App\Domain\IAM\Enums;

enum PositionSource: string
{
    case SYSTEM_TEMPLATE = 'system_template';
    case TENANT_CUSTOM = 'tenant_custom';
}
