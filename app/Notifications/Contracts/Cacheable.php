<?php

namespace App\Notifications\Contracts;

interface Cacheable
{
    /**
     * Get the unique cache key for the notification.
     *
     * @param  mixed  $notifiable
     * @return string|array
     */
    public function cacheKey($notifiable): string|array;
}
