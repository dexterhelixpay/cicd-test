<?php

namespace App\Channels;

use App\Notifications\Contracts\Cacheable;
use App\Notifications\Contracts\SendsMail;
use App\Notifications\Contracts\SendsSms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class CacheChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, $notification)
    {
        if (
            !setting('CachedNotificationKey', '')
            || !($notification instanceof Cacheable)
        ) {
            return;
        }

        $data = [
            'notification' => is_callable([$notification, 'toArray'])
                ? call_user_func([$notification, 'toArray'], $notifiable)
                : [],
        ];

        if (!count(Arr::wrap($data['notification']))) {
            return;
        }

        if ($notifiable instanceof Model) {
            data_set($data, 'notifiable.id', $notifiable->getKey());
        }

        if ($notification instanceof SendsMail) {
            data_set($data, 'notifiable.email', $notifiable->routeNotificationFor('mail'));
        }

        if ($notification instanceof SendsSms) {
            data_set($data, 'notifiable.mobile_number', $notifiable->routeNotificationFor('sms'));
        }

        collect($notification->cacheKey($notifiable))
            ->each(function ($key) use ($data) {
                Cache::tags('notifications')->put($key, $data, 1800);
            });
    }
}
