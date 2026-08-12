<?php

namespace App\Domain\Communication\Enums;

enum OtpChannel: string
{
    case SMS = 'sms';
    case EMAIL = 'email';
    // WHATSAPP and VOICE are deferred to future waves
}
