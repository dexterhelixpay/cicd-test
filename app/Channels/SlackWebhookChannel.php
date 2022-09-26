<?php

namespace App\Channels;

use Illuminate\Notifications\Channels\SlackWebhookChannel as Channel;
use Illuminate\Support\Arr;

class SlackWebhookChannel extends Channel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return \Psr\Http\Message\ResponseInterface|null
     */
    public function send($notifiable, $notification)
    {
        $urls = is_callable([$notification, 'slackWebhooks'])
            ? call_user_func([$notification, 'slackWebhooks'])
            : $notifiable->routeNotificationFor('slack', $notification);

        if (!count($urls = Arr::wrap($urls))) return;

        foreach ($urls as $url) {
            return $this->http->post($url, $this->buildJsonPayload(
                call_user_func([$notification, 'toSlack'], $notifiable)
            ));
        }
    }
}
