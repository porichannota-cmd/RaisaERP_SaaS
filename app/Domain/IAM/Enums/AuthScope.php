<?php

namespace App\Domain\IAM\Enums;

enum AuthScope: string
{
    case PLATFORM = 'PLATFORM';
    case TENANT = 'TENANT';
    case COMPANY = 'COMPANY';
    case BRANCH = 'BRANCH';
    case DEPARTMENT = 'DEPARTMENT';
    case WAREHOUSE = 'WAREHOUSE';
    case REGION = 'REGION';
    case AREA = 'AREA';
    case DISTRICT = 'DISTRICT';
    case THANA = 'THANA';
    case UNION = 'UNION';
    case OWN = 'OWN';
    case REPORTING_TREE = 'REPORTING_TREE';

    public function requiresScopeId(): bool
    {
        return match ($this) {
            self::PLATFORM, self::TENANT, self::OWN, self::REPORTING_TREE => false,
            default => true,
        };
    }
}
