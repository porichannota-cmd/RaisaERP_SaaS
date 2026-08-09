<?php

namespace App\Logging;

use Monolog\LogRecord;

class SecurityRedactionProcessor
{
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'authorization',
        'cookie',
        'api_key'
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        if (empty($context)) {
            return $record;
        }

        $redactedContext = $this->redact($context);
        
        return $record->with(context: $redactedContext);
    }

    private function redact(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->redact($value);
                continue;
            }

            if (is_string($key)) {
                foreach ($this->sensitiveKeys as $sensitiveKey) {
                    if (str_contains(strtolower($key), $sensitiveKey)) {
                        $value = '[REDACTED]';
                        break;
                    }
                }
            }
        }

        return $data;
    }
}
