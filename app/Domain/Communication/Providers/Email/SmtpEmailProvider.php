<?php

namespace App\Domain\Communication\Providers\Email;

use App\Domain\Communication\Contracts\EmailProviderInterface;
use App\Domain\Communication\DTOs\DeliveryResult;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Laravel SMTP Email Provider.
 * Uses the configured Laravel mail transport (log driver in test environments).
 */
class SmtpEmailProvider implements EmailProviderInterface
{
    public function send(string $to, string $subject, string $body): DeliveryResult
    {
        try {
            Mail::raw($body, function (Message $message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return DeliveryResult::success($this->providerName());
        } catch (\Throwable $e) {
            return DeliveryResult::temporaryFailure($this->providerName(), 'smtp_error');
        }
    }

    public function providerName(): string
    {
        return 'smtp';
    }
}
