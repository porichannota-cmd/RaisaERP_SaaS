<?php

namespace App\Domain\Communication\Enums;

enum DestinationType: string
{
    case MOBILE = 'mobile';
    case EMAIL = 'email';
}
