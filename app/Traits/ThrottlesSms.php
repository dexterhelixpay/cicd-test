<?php

namespace App\Traits;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

trait ThrottlesSms
{
    /**
     * Get the throttle keys for SMS.
     *
     * @return array
     */
    public function getSmsThrottleKeys()
    {
        $user = request()->user();

        if ($user = request()->user()) {
            $class = Str::snake(Arr::last(explode('\\', get_class($user))));

            $identifier = "{$class}:{$user->getKey()}";
        } elseif (count($ips = Request::ips())) {
            $identifier = Arr::last($ips);
        } else {
            $identifier = Request::ip();
        }

        return [
            'time' => "sms:{$identifier}:time",
            'count' => "sms:{$identifier}:count",
        ];
    }

    /**
     * Check if the SMS request is throttled.
     *
     * @param  \App\Models\Customer|null  $customer
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    public function isSmsThrottled()
    {
        $keys = $this->getSmsThrottleKeys();
        $attempts = 5;

        if (Cache::get($keys['count'], 0) >= $attempts) {
            if (Cache::has($keys['time'])) {
                $duration = Carbon::parse(Cache::get($keys['time']))->diffForHumans();
                $message = "You have many OTP attempts. Try again {$duration}.";

                throw new ThrottleRequestsException($message, null, [
                    'Gw-Retry-After' => Cache::get($keys['time']),
                ], 30);
            }

            Cache::forget($keys['count']);
        }
    }

    /**
     * Throttle the SMS request.
     *
     * @param  callable|null  $callback
     * @return mixed
     */
    public function throttleSms($callback = null)
    {
        $this->isSmsThrottled();

        $return = $callback ? $callback() : null;

        $keys = $this->getSmsThrottleKeys();
        $decay = 60;
        $timeAvailable = now()->addSeconds($decay);

        Cache::add($keys['time'], $timeAvailable->toDateTimeString(), $decay);

        $added = Cache::add($keys['count'], 0, $decay);
        $hits = (int) Cache::increment($keys['count']);

        if (!$added && $hits === 1) {
            Cache::put($keys['count'], 1, $decay);
        }

        return $return;
    }
}
