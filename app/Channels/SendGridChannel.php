<?php

namespace App\Channels;

use App\Traits\TracksEmail;
use App\Messages\SendGridMail;
use App\Support\Model as ModelHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use SendGrid;

class SendGridChannel
{
    /**
     * The SendGrid instance.
     *
     * @var \SendGrid
     */
    protected $sendGrid;

    /**
     * Create a new channel instance.
     *
     * @param  \SendGrid  $sendGrid
     * @return void
     */
    public function __construct(SendGrid $sendGrid)
    {
        $this->sendGrid = $sendGrid;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \App\Notifications\Contracts\SendsMail  $notification
     * @return void
     */
    public function send($notifiable, $notification)
    {
        if (!app()->environment('staging', 'sandbox', 'production', 'development')) return;

        if (!$email = $this->getEmail($notifiable, $notification)) {
            return;
        }

        if (!$message = $notification->toMail($notifiable)) {
            return;
        }

        if ($message instanceof MailMessage || $message instanceof Mailable) {
            $message = SendGridMail::fromMail($message);
        }

        if (!$message instanceof SendGridMail) {
            return;
        }

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        if (
            $notifiable instanceof Model
            && ModelHelper::hasRelation($notifiable, 'merchant')
            && ($merchant = $notifiable->merchant()->first())
        ) {
            $fromAddress = $merchant->subdomain
                ? "{$merchant->subdomain}@" . Arr::last(explode('@', $fromAddress))
                : $fromAddress;

            $fromName = $merchant->name ?? $fromName;
        }

        if (
            in_array(TracksEmail::class, class_uses($notification))
            && $emailInfo = $notification->emailInfo()
        ) {
            collect($emailInfo ?? [])
                ->each(function ($value, $key) use(&$message) {
                    $message->addCustomArg($key, (string) $value);
                });
        }

        $message->setFrom($fromAddress, $fromName);
        $message->addTo($email);
        $message->setOpenTracking(true);
        $message->setClickTracking(true, true);

        $response = $this->sendGrid->send($message);

        if ($response->statusCode() >= 400) {
            Log::error('SendGrid Error', [
                'status' => $response->statusCode(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);
        }
    }

    /**
     * Get the recipient's email.
     *
     * @param  mixed  $notifiable
     * @param  \App\Notifications\Contracts\SendsMail  $notification
     * @return string|null
     */
    protected function getEmail($notifiable, $notification)
    {
        if ($email = $notifiable->routeNotificationFor('mail', $notification)) {
            return $email;
        }

        return data_get($notifiable, 'email');
    }
}
