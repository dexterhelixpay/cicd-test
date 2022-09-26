<?php

namespace App\Channels;

use App\Facades\GsmConverter;
use App\Models\SmsLog;
use App\Notifications\Contracts\SendsConfidentialSms;
use App\Rules\MobileNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GlobeLabsSmsChannel
{
    /**
     * The URI for sending SMS.
     *
     * @var string
     */
    private $sendUri = 'https://api.m360.com.ph/v3/api/globelabs/mt/:clientPassphrase';

    /**
     * A set of characters to be replaced with safe ones.
     *
     * @var array
     */
    private $replaceChars = [
        '₱' => 'P',
    ];

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \App\Notifications\Contracts\SendsSms  $notification
     * @return void
     */
    public function send($notifiable, $notification)
    {
        if (!app()->environment('development', 'staging', 'sandbox', 'production')) return;

        if (!$mobileNumber = $notifiable->routeNotificationFor('sms', $notification)) {
            return;
        }

        if (!(new MobileNumber)->passes('', $mobileNumber)) {
            return;
        }

        $sendUri = preg_replace_array('/:[a-zA-Z]+/', [
            config('services.m360.passphrase'),
        ], $this->sendUri);

        $message = (string) $notification->toSms($notifiable);
        $logMessage = $notification instanceof SendsConfidentialSms
            ? (string) $notification->toEncryptedSms($notifiable)
            : $message;

        $log = SmsLog::make([
            'mobile_number' => mobile_number($mobileNumber),
            'body' => $logMessage,
        ]);

        $response = Http::asJson()
            ->timeout(60)
            ->post($sendUri, [
                'outboundSMSMessageRequest' => [
                    'senderAddress' => config('services.m360.sender_address'),
                    'address' => mobile_number($mobileNumber),
                    'outboundSMSTextMessage' => [
                        'message' => $this->cleanMessage($message),
                    ],
                    'clientCorrelator' => Str::random(),
                ],
            ]);

        $onError = function ($response) use ($notifiable, $notification, $log) {
            $log->fill([
                'error_info' => [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ],
                'is_successful' => false,
            ]);

            $this->fallbackAsMail($notifiable, $notification);
        };

        $response->onError($onError);

        if ($response->successful() && $response->json('code')) {
            $onError($response);
        }

        $log->save();
    }

    /**
     * Clean-up the given message to comply with GSM 03.38.
     *
     * @param  string  $message
     * @return string
     */
    protected function cleanMessage($message)
    {
        foreach ($this->replaceChars as $char => $replace) {
            $message = preg_replace("/{$char}/", $replace, $message);
        }

        return GsmConverter::cleanUpUtf8String($message, true, '');
    }

    /**
     * Send an email if SMS sending fails.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    protected function fallbackAsMail($notifiable, $notification)
    {
        if (count(array_intersect(['mail', 'sendgrid'], $notification->via($notifiable) ?? []))) {
            return;
        }

        $notifiable->notifyNow($notification, app()->isLocal() ? ['mail'] : ['sendgrid']);
    }
}
