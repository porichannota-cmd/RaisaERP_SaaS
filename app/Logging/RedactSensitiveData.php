<?php

namespace App\Logging;

use Illuminate\Log\Logger;

class RedactSensitiveData
{
    /**
     * Customize the given logger instance.
     */
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new SecurityRedactionProcessor());
        }
    }
}
