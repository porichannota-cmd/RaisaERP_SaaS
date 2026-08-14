<?php

declare(strict_types=1);

namespace App\Domain\Business\Enums;

enum ProvisioningStatus: string
{
    case DRAFT = 'DRAFT';
    case READY_FOR_PROVISIONING = 'READY_FOR_PROVISIONING';
    case PROVISIONED = 'PROVISIONED';
}
