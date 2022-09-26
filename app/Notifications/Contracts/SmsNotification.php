<?php

namespace App\Notifications\Contracts;

use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

abstract class SmsNotification extends Notification implements SendsSms
{
    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return app()->isProduction() ? ['m360'] : ['slack', 'cache'];
    }

    /**
     * Get the Slack webhook URLs.
     *
     * @return string|array|null
     */
    public function slackWebhooks()
    {
        return config('services.slack.sms_webhook_url');
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\SlackMessage
     */
    public function toSlack($notifiable)
    {
        return (new SlackMessage)
            ->success()
            ->content('An SMS message has been successfully sent.')
            ->attachment(function ($attachment) use ($notifiable) {
                $fields = [
                    'time' => now()->toDateTimeString(),
                    'class' => get_class($this),
                ];

                $content = $this instanceof SendsConfidentialSms
                    ? $this->toEncryptedSms($notifiable)
                    : $this->toSms($notifiable);

                if (method_exists($this, 'toArray')) {
                    $fields = array_merge(
                        call_user_func([$this, 'toArray'], $notifiable),
                        $fields
                    );
                }

                $attachment
                    ->title($notifiable->routeNotificationFor('sms'))
                    ->content((string) $content)
                    ->fields($fields);
            });
    }
}
