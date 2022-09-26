<?php

namespace App\Channels;

use App\Facades\GsmConverter;
use App\Models\SmsLog;
use App\Notifications\Contracts\SendsConfidentialSms;
use App\Rules\MobileNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class M360Channel
{
    /**
     * The URI for sending SMS.
     *
     * @var string
     */
    public $apiUrl;

    /**
     * The username.
     *
     * @var string
     */
    public $username;

    /**
     * The password.
     *
     * @var string
     */
    public $password;

    /**
     * The masked shortcode.
     *
     * @var string
     */
    public $shortcodeMask;

    /**
     * A set of characters to be replaced with safe ones.
     *
     * @var array
     */
    private $replaceChars = [
        '₱' => 'P',
    ];

    /**
     * Create a new channel instance.
     *
     * @param  string  $apiUrl
     * @param  string  $username
     * @param  string  $password
     * @param  string  $shortcodeMask
     * @return void
     */
    public function __construct($apiUrl, $username, $password, $shortcodeMask)
    {
        $this->apiUrl = $apiUrl;
        $this->username = $username;
        $this->password = $password;
        $this->shortcodeMask = $shortcodeMask;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \App\Notifications\Contracts\SendsSms  $notification
     * @return void
     */
    public function send($notifiable, $notification)
    {
        if (!app()->environment('development', 'staging', 'sandbox', 'production')) {
            return;
        }

        if (!$mobileNumber = $notifiable->routeNotificationFor('sms', $notification)) {
            return;
        }

        if (!(new MobileNumber)->passes('', $mobileNumber)) {
            return;
        }

        if (!$message = (string) $notification->toSms($notifiable)) {
            return;
        }

        $logMessage = $notification instanceof SendsConfidentialSms
            ? (string) $notification->toEncryptedSms($notifiable)
            : $this->cleanMessage($message);

        $log = SmsLog::make([
            'mobile_number' => mobile_number($mobileNumber),
            'body' => $logMessage,
        ]);

        $response = Http::asJson()
            ->timeout(60)
            ->post($this->apiUrl, [
                'username' => $this->username,
                'password' => $this->password,
                'shortcode_mask' => $this->shortcodeMask,
                'msisdn' => mobile_number($mobileNumber),
                'content' => $this->cleanMessage($message),
                'is_intl' => false,
            ]);

        $response->onError(function ($response) use ($notifiable, $notification, $log) {
            $log->fill([
                'error_info' => [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ],
                'is_successful' => false,
            ]);

            $this->fallbackAsMail($notifiable, $notification);
        });

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
        $channels = is_callable([$notification, 'via'])
            ? call_user_func([$notification, 'via'], $notifiable)
            : [];

        if (count(array_intersect(['mail', 'sendgrid'], $channels))) {
            return;
        }

        if (is_callable([$notification, 'toMail'])) {
            $notifiable->notifyNow($notification, app()->isLocal() ? ['mail'] : ['sendgrid']);
        }
    }
}
