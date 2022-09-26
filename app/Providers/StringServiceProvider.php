<?php

namespace App\Providers;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class StringServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Str::macro('mobileNumber', function ($value, $useCountryCode = false) {
            if (!$value || empty($value)) return null;

            $value = preg_replace('/[^\+0-9]/', '', $value);

            return preg_replace('/^(\+?63|0)/', $useCountryCode ? '+63' : '', $value);
        });

        Str::macro('makeReplacements', function ($text, Order $order) {
            $subscription = $order->subscription;
            $merchant = $subscription->merchant;

            $text = str_replace(
                '{isWas}',
                $order->billing_date->isBefore(now()->startOfDay()) ? 'was' : 'is',
                $text
            );

            if (preg_match('/{(subscription|subscriptionTermSingular)}/i', $text)) {
                if ($order->isSingle()) {
                    $term = 'order';
                } else {
                    $term = $merchant->subscription_term_singular ?? 'subscription';
                }

                $text = strtr($text, [
                    '{Subscription}' => Str::ucfirst($term),
                    '{SUBSCRIPTION}' => Str::upper($term),
                    '{subscription}' => $term,
                    '{SubscriptionTermSingular}' => Str::ucfirst($term),
                    '{SUBSCRIPTIONTERMSINGULAR}' => Str::upper($term),
                    '{subscriptionTermSingular}' => $term,
                ]);
            }

            if (preg_match('/{subscriptions|subscriptionTermPlural}/i', $text)) {
                $term = $merchant->subscription_term_singular ?? 'subscriptions';

                $text = strtr($text, [
                    '{Subscriptions}' => Str::ucfirst($term),
                    '{SUBSCRIPTIONS}' => Str::upper($term),
                    '{subscriptions}' => $term,
                    '{SubscriptionTermPlural}' => Str::ucfirst($term),
                    '{SUBSCRIPTIONTERMPLURAL}' => Str::upper($term),
                    '{subscriptionTermPlural}' => $term,
                ]);
            }

            if (preg_match('/{(startContinue|startOrContinue)}/i', $text)) {
                $term = $order->isInitial() ? 'start' : 'continue';

                $text = strtr($text, [
                    '{StartContinue}' => Str::ucfirst($term),
                    '{STARTCONTINUE}' => Str::upper($term),
                    '{startContinue}' => $term,
                    '{StartOrContinue}' => Str::ucfirst($term),
                    '{STARTORCONTINUE}' => Str::upper($term),
                    '{startOrContinue}' => $term,
                ]);
            }

            return $text;
        });

        Str::macro('slugFor', function (Model $model, $from = 'title', $saveAs = 'slug') {
            $slug = Str::slug($model->getAttribute($from));
            $count = 1;

            $slugQuery = fn ($slug) => $model
                ->newQuery()
                ->where($saveAs, $slug)
                ->when($model->exists, fn ($query) => $query->whereKeyNot($model));

            while ($slugQuery($slug)->exists()) {
                $slug = Str::slug($model->getAttribute($from) . ' ' . $count++);
            }

            return $model->setAttribute($saveAs, $slug);
        });

        Str::macro('splitName', function ($name, $useFirstAsLast = true) {
            if (!is_string($name)) {
                return null;
            }

            if (!mb_strlen($name = trim($name))) {
                return null;
            }

            $nameParts = collect(explode(' ', $name))
                ->filter(function ($part) {
                    return mb_strlen($part) > 0;
                });

            if ($nameParts->isEmpty()) {
                return null;
            }

            if ($nameParts->count() > 1) {
                $firstName = $nameParts->shift();

                return [
                    'firstName' => $firstName,
                    'lastName' => $nameParts->join(' '),
                ];
            }

            return [
                'firstName' => $nameParts->first(),
                'lastName' => $useFirstAsLast ? $nameParts->first() : null,
            ];
        });

        Str::macro('validEmail', function ($email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            return checkdnsrr(Arr::last(explode('@', $email)), 'MX')
                ? $email
                : false;
        });
    }
}
