<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Enums\DestinationType;
use App\Domain\Communication\Exceptions\InvalidDestinationException;

class DestinationNormalizer
{
    /**
     * Normalize a Bangladesh mobile number to canonical E.164 format: +8801XXXXXXXXX
     *
     * Accepts:
     *   01XXXXXXXXX     (11 digits)
     *   8801XXXXXXXXX   (13 digits)
     *   +8801XXXXXXXXX  (14 chars including +)
     */
    public function normalizeMobile(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        // Strip leading country code 880 if present (leaving 10 digits starting with 1)
        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 3);
        }

        // Ensure leading zero if missing
        if (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        // Now we expect 11 digits starting with 01
        if (! preg_match('/^01[3-9]\d{8}$/', $digits)) {
            throw new InvalidDestinationException(
                'Invalid Bangladesh mobile number: '.$this->maskMobile($raw)
            );
        }

        return '+880'.substr($digits, 1);
    }

    /**
     * Normalize an email address.
     * Conservative: lowercase only. Does not strip dots or plus-addressing
     * to avoid destroying valid distinct addresses.
     */
    public function normalizeEmail(string $raw): string
    {
        $normalized = mb_strtolower(trim($raw));

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidDestinationException(
                'Invalid email address: '.$this->maskEmail($raw)
            );
        }

        return $normalized;
    }

    /**
     * Normalize by type.
     */
    public function normalize(string $raw, DestinationType $type): string
    {
        return match ($type) {
            DestinationType::MOBILE => $this->normalizeMobile($raw),
            DestinationType::EMAIL => $this->normalizeEmail($raw),
        };
    }

    /**
     * Mask a mobile number for safe logging: 017******61
     */
    public function maskMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);
        if (strlen($digits) < 4) {
            return '***';
        }

        return substr($digits, 0, 3).str_repeat('*', max(0, strlen($digits) - 5)).substr($digits, -2);
    }

    /**
     * Mask an email address for safe logging: r***@example.com
     */
    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        if (strlen($local) <= 1) {
            return '*@'.$domain;
        }

        return $local[0].str_repeat('*', max(1, strlen($local) - 1)).'@'.$domain;
    }
}
