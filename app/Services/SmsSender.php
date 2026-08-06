<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsSender
{
    public function send(string $to, string $message): void
    {
        if (config('services.sms.driver') === 'log') {
            if (app()->environment('production')) {
                throw new RuntimeException('SMS delivery is not configured. Set SMS_DRIVER=twilio and add the Twilio credentials.');
            }

            Log::info('SMS message', ['to' => $to, 'message' => $message]);

            return;
        }

        if (config('services.sms.driver') !== 'twilio') {
            throw new RuntimeException('The SMS provider is not configured.');
        }

        $accountSid = config('services.sms.twilio.account_sid');
        $authToken = config('services.sms.twilio.auth_token');
        $from = config('services.sms.twilio.from');

        if (! $accountSid || ! $authToken || ! $from) {
            throw new RuntimeException('The Twilio SMS credentials are incomplete.');
        }

        $response = Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                'From' => $from,
                'To' => $to,
                'Body' => $message,
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->json('message') ?: 'The SMS provider could not send the code.');
        }
    }
}
