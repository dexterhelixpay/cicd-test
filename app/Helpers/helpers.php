<?php

use App\Exceptions\BadRequestException;
use App\Models\MerchantRecurrence;
use App\Models\Setting;
use App\Models\SubscribedProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Vinkla\Hashids\Facades\Hashids;

if (!function_exists('replace_placeholders')) {
    /**
     * Replace placeholders in text
     *
     * @param  string  $text
     * @param  App\Models\Order  $order
     * @param  string|null  $application
     * @return string|null
     */
    function replace_placeholders($text, $order, $application = null)
    {
        if (empty($text)) {
            return null;
        }

        $subscription = $order->subscription;
        $merchant = $subscription->merchant;

        $month = Carbon::parse($order->billing_date)->format('F');
        $day = ordinal_number(Carbon::parse($order->billing_date)->format('j'));
        $billingDate = "{$month} {$day}";

        $nextBillingDate = $subscription->nextOrder?->billing_date->format('F j');

        $customerProfilePage = 'customer profile page';

        if ($application == 'email') {
            $customerProfileUrl = config('bukopay.url.profile');
            $href ="https://{$merchant->subdomain}.{$customerProfileUrl}";
            $color = $merchant->on_background_color ?? 'black';

            $customerProfilePage = "<a style='
                text-decoration: none !important;
                font-weight: 700;
                color: $color
                !important' href='$href'
                target='_blank'>customer profile page</a>";
        }

        $text = str_replace(
            '{customerProfilePage}', $customerProfilePage, $text
        );
        $text = str_replace(
            '{customerName}', $subscription?->customer?->name, $text
        );

        $text = str_replace(
            '{billingDate}', $billingDate, $text
        );

        $text = str_replace(
            '{nextBillingDate}', $nextBillingDate ?: $billingDate, $text
        );

        $text = str_replace(
            '{merchantName}', $merchant->name, $text
        );

        $text = str_replace(
            '{subscriptionId}', formatId($subscription->created_at, $subscription->id), $text
        );

        $text = str_replace(
            '{orderId}', formatId($order->created_at, $order->id), $text
        );

        $text = str_replace(
            '{totalPrice}', number_format($order->total_price, 2, '.', ','), $text
        );

        return Str::makeReplacements($text, $order);
   }
}

if (!function_exists('sort_of_round')) {
    /**
    * Round numbers like sm discount
    *
    * @param  mixed  $value
    *
    * @return mixed
    */
   function sort_of_round($value)
   {
        if (
            strpos($value, ".") !== false
            && is_numeric( $value )
            && floor( $value ) != $value
        ) {
            return round($value + 0.01) - 0.01;
        }

        return $value;
   }
}

if (!function_exists('snakeCase')) {
    /**
    * Format text to snake case
    *
    * @param  mixed  $string
    *
    * @return mixed
    */
   function snakeCase($string)
   {
        if (is_array($string)) {
            return collect($string)
                ->map(function ($text) {
                    return Str::snake($text);
                })->toArray();
        }

        return Str::snake($string);
   }
}

if (!function_exists('abbreviateName')) {
    /**
    * Format Name
    *
    * @param  string  $name
    *
    * @return array
    */
   function abbreviateName($name)
   {
       $rawName = explode(' ', $name);;

       if (count($rawName) == 1) return $name;

       $lastName = substr(Arr::last($rawName), 0, 1).'.';

       return Arr::first($rawName).' '.$lastName;
   }
}

if (!function_exists('formatShopifyRecurrences')) {
    /**
    * Format Shopify Recurrences
    *
    * @param  \App\Models\Merchant  $merchant
    *
    * @return array
    */
   function formatShopifyRecurrences($merchant)
    {
        return $merchant->recurrences()
            ->where('is_enabled', true)
            ->where('code', '!=', 'annually')
            ->get()
            ->sortBy('sort_number')
            ->map(function (MerchantRecurrence $merchantRecurrence) {
                return [
                    'code' =>  $merchantRecurrence->code,
                    'label' =>  $merchantRecurrence->name,
                    'discountValue' =>  null,
                    'discountType' =>  null,
                ];
            });
   }
}

if (!function_exists('formatProducts')) {

    /**
    * Format Products
    *
    * @param  $products
    *
    * @return string
    */
    function formatProducts($products)
    {
       return $products->map(function ($product) {
            return $product->title;
        })->join(', ', ' and ');
    }
}

if (!function_exists('formatRecurrenceText')) {
    /**
    * Format Shopify Recurrences
    *
    * @param  \App\Models\Merchant  $merchant
    *
    * @return array
    */
   function formatRecurrenceText($recurrence, $merchant)
    {

        $subscriptionTerm = ucwords($merchant->subscription_term_singular);

       switch ($recurrence) {
           case 'weekly':
               $formattedText = "Weekly {$subscriptionTerm}";
               break;

           case 'semimonthly':
               $formattedText = "Every Other Week {$subscriptionTerm}";
               break;

           case 'monthly':
               $formattedText = "Monthly {$subscriptionTerm}";
               break;

           case 'quarterly':
               $formattedText = "Quarterly {$subscriptionTerm}";
               break;

           case 'annually':
               $formattedText = "Annual {$subscriptionTerm}";
               break;

           case 'bimonthly':
               $formattedText = "Every 2 months {$subscriptionTerm}";
                break;

           case 'semiannual':
                $formattedText = "Semi Annual {$subscriptionTerm}";
                break;

           default:
               $formattedText = 'Single Order';
               break;
       }

       return $formattedText;
   }
}


if (!function_exists('dayOfWeek')) {
    /**
    * Format day of week to text
    *
    * @param  int  $name
    *
    * @return array
    */
   function dayOfWeek($dayOfWeek)
   {
      switch ($dayOfWeek) {
        case 0:  return 'sunday';
        case 1:  return 'monday';
        case 2:  return 'tuesday';
        case 3:  return 'wednesday';
        case 4:  return 'thursday';
        case 5:  return 'friday';
        case 6:  return 'saturday';
      }
   }
}

if (!function_exists('ordinal_number')) {
    /**
     * Format to Ordinal Number
     *
     * @param  int  $num
     * @return string
     */
    function ordinal_number($num)
    {
        if (!in_array(($num % 100),array(11,12,13)))
        {
          switch ($num % 10) {
            // Handle 1st, 2nd, 3rd
            case 1:  return $num.'st';
            case 2:  return $num.'nd';
            case 3:  return $num.'rd';
          }
        }
        return $num.'th';
    }
}


if (!function_exists('is_date')) {
    /**
     * Check if the given value is a date based on the given format.
     *
     * @param  string  $value
     * @param  string  $format
     * @return bool
     */
    function is_date($value, $format = 'Y-m-d'): bool
    {
        try {
            $date = Carbon::createFromFormat($format, $value);

            return $date && $value === $date->format($format);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('are_all_single_recurrence')) {
    /**
     * Checks if all products are with single recurrence
     *
     * @param  array  $products
     * @return bool
     */
    function are_all_single_recurrence($products)
    {
        return collect($products)
            ->every(function (SubscribedProduct $subscribedProduct) {
                return data_get($subscribedProduct->payment_schedule, 'frequency', null) == 'single';
            }) > 0;
    }
}

if (!function_exists('are_shippable_products')) {
    /**
     * Checks if products are shippable
     *
     * @param  array  $products
     * @return bool
     */
    function are_shippable_products($products, $type = null)
    {
        if ($type == 'console') {
            return collect($products)
                ->contains('attributes.is_shippable',true);
        }

        return collect($products)
            ->contains('is_shippable',true);
    }
}

if (!function_exists('excel_date')) {
    /**
     * Transform the specified Excel date into a Carbon instance.
     *
     * @param  mixed  $values
     * @param  string  $format
     * @return \Illuminate\Support\Carbon|null
     */
    function excel_date($value, string $format = 'Y-m-d'): ?Carbon
    {
        if (empty($value)) return null;

        try {
            return Carbon::instance(Date::excelToDateTimeObject($value));
        } catch (Throwable $e) {
            return Carbon::createFromFormat($format, $value);
        }
    };
}

if (!function_exists('assoc_table')) {
    /**
     * Convert the given array to a table-friendly format.
     *
     * @param  array  $array
     * @return array
     */
    function assoc_table($array)
    {
        $index = 0;

        return collect($array)
            ->mapWithKeys(function ($value, $key) use (&$index) {
                return [$index++ => [
                    $key,
                    is_bool($value)
                        ? ($value ? 'true' : 'false')
                        : join(', ', (array) $value),
                ]];
            })
            ->toArray();
    }
}

if (! function_exists('data_has')) {
    /**
     * Find if there's an item in an array or object using "dot" notation.
     *
     * @param  mixed  $target
     * @param  string|array|int  $keys
     * @return bool
     */
    function data_has($target, $keys): bool
    {
        if (! $target || is_null($keys)) {
            return false;
        }

        if (! count($keys = (array) $keys)) {
            return false;
        }

        foreach ($keys as $i => $key) {
            $subKeyTarget = $target;

            if (Arr::accessible($subKeyTarget) && Arr::exists($subKeyTarget, $key)) {
                continue;
            }

            if (is_object($subKeyTarget) && Arr::exists(get_object_vars($subKeyTarget), $key)) {
                continue;
            }

            foreach (explode('.', $key) as $segment) {
                if ($segment === '*') {
                    if ($subKeyTarget instanceof Collection) {
                        $subKeyTarget = $subKeyTarget->all();
                    } elseif (!is_array($subKeyTarget)) {
                        return false;
                    }

                    if (empty($key)) {
                        return true;
                    }

                    return array_reduce($subKeyTarget, function ($present, $item) use ($keys, $i) {
                        return $present || data_has($item, array_slice($keys, $i + 1));
                    }, false);
                }

                if (Arr::accessible($subKeyTarget) && Arr::exists($subKeyTarget, $segment)) {
                    $subKeyTarget = $subKeyTarget[$segment];
                } elseif (is_object($subKeyTarget) && isset($subKeyTarget->{$segment})) {
                    $subKeyTarget = $subKeyTarget->{$segment};
                } else {
                    return false;
                }
            }
        }

        return true;
    }
}

if (!function_exists('decodeId')) {
    /**
    * Decode hash ids
    *
    * @param  string  $id
    * @param  string  $connection
    *
    * @return string
    */
   function decodeId($id, $connection)
   {
    return Hashids::connection($connection)->decode($id);
   }
}

if (!function_exists('hashId')) {
    /**
    * Hash ids
    *
    * @param  string  $id
    * @param  string  $connection
    *
    * @return string
    */
   function hashId($id, $connection)
   {
    return Hashids::connection($connection)->encode($id);
   }
}

if (!function_exists('formatId')) {
    /**
    * Format Transaction or order id
    *
    * @param  string  $date
    * @param  string  $id
    *
    * @return array
    */
   function formatId($date, $id)
   {
       $year = Carbon::parse($date)->format('y');
       $seconds = Carbon::parse($date)->format('s');

      return "{$year}{$seconds}{$id}";
   }
}

if (!function_exists('env_array')) {
    /**
     * Gets the value of an environment variable as an array.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function env_array($key, $default = null)
    {
        $value = env($key, $default);
        if (is_null($value)) return $value;

        return is_array($value) ? $value : explode(',', $value);
    }
}

if (!function_exists('mobile_number')) {
    /**
     * Normalize the given mobile number.
     *
     * @param  mixed  $value
     * @param  bool  $useCountryCode
     * @return
     */
    function mobile_number($value, $useCountryCode = false): ?string
    {
        if (!$value || empty($value)) return null;

        $value = preg_replace('/[^\+0-9]/', '', $value);

        return preg_replace('/^(\+63|0)/', $useCountryCode ? '+63' : '0', $value);
    }
}

if (!function_exists('oauth_client')) {
    /**
     * Get the OAuth client of the specified provider.
     *
     * @param  string|null  $name
     * @return stdClass|null
     */
    function oauth_client($provider = null)
    {
        $provider = $provider ?? config('auth.defaults.passwords');

        return Cache::tags('oauth_clients')
            ->remember($provider, 86400, function () use ($provider) {
                $clientName = ucwords(str_replace('_', ' ', $provider));
                $clientName .= ' Password Grant Client';

                return DB::table('oauth_clients')->where('name', $clientName)->first();
            });
    }
}

if (!function_exists('setting')) {
    /**
     * Get the specified setting value.
     *
     * @param  string|array  $key
     * @param  mixed  $default
     * @return mixed
     */
    function setting($key, $default = null)
    {
        $setting = Setting::where('key', $key)->first();

        return optional($setting)->value ?? $default;
    }
}

if (!function_exists('smi')) {
    /**
     * Get the sub-merchant ID based on the card type.
     *
     * @param  string  $cardType
     * @return string|null
     */
    function smi($cardType)
    {
        switch ($cardType) {
            case 'master-card':
                return config('services.paymaya.metadata.smi.mastercard');

            case 'jcb':
                return config('services.paymaya.metadata.smi.jcb');

            case 'visa':
            default:
                return config('services.paymaya.metadata.smi.visa');
        }
    }
}

if (!function_exists('smn')) {
    /**
     * Cleanup the given string for `smn` usage.
     *
     * @param  string  $value
     * @return string
     */
    function smn($value)
    {
        return mb_substr(Str::ucfirst(Str::camel(Str::slug($value))), 0, 9);
    }
}

if (!function_exists('subdomain')) {
    /**
     * Get the subdomain prefixed by the current environment.
     *
     * @param  string  $subdomain
     * @return string
     */
    function subdomain($subdomain)
    {
        $env = app()->environment();

        if (in_array($env, ['local', 'production'])) {
            return $subdomain;
        }

        if ($env === 'development') {
            return "dev-{$subdomain}";
        }

        return "{$env}-{$subdomain}";
    }
}

if (!function_exists('start_or_continue')) {
    /**
     * Get the sub-merchant ID based on the card type.
     *
     * @param  object  $subscription
     * @param  string  $orderId
     * @param  string  $textCase
     * @return string
     */
    function start_or_continue($subscription, $orderId, $textCase = 'lowercase')
    {
        $initialOrder = $subscription->initialOrder()->first();
        $start = 'start';
        $continue = 'continue';

        if ($textCase !== 'lowercase') {
            $start = ucwords($start);
            $continue = ucwords($continue);
        }

        return $initialOrder->id == $orderId
            ? $start
            : $continue;
    }
}

if (!function_exists('pay_button_text')) {
    /**
     * Get pay now button text based on order type
     *
     * @param  object  $subscription
     * @param  string  $orderId
     * @return string
     */
    function pay_button_text($subscription, $orderId, $buttonLabel = 'Pay Now')
    {
        $initialOrder = $subscription->initialOrder()->first();

        return $initialOrder
            && $initialOrder->id == $orderId
            && are_all_single_recurrence($subscription->products)
                ? $subscription->merchant->pay_button_text ?? $buttonLabel
                : $subscription->merchant->recurring_button_text ?? 'Start Subscription';
    }
}

if (!function_exists('shopify_link_file_type')) {
    /**
     * Get shopify link file type
     *
     * @param  string  $extension
     * @return string
     */
    function shopify_link_file_type($extension)
    {
        $images = ['jpg', 'jpeg', 'gif', 'png'];
        $documents = ['pdf', 'doc', 'docx', 'gdoc', 'html', 'md', 'odt', 'ott', 'rtf', 'txt'];

        if (in_array($extension, $images)) {
            return 'image';
        } else if (in_array($extension, $documents)) {
            return 'document';
        }

        return 'image';
    }
}

if (!function_exists('format_payment_schedule')) {
    /**
     * Format payment schedule
     *
     * @param  string  $extension
     * @return string
     */
    function format_payment_schedule($frequency, $billingDate)
    {
        if (!$billingDate) {
            throw new BadRequestException('Billing date is invalid.');
        }

        switch ($frequency) {
            case 'weekly':
            case 'semimonthly':

                return [
                    'frequency' => $frequency,
                    'day_of_week' => $billingDate->dayOfWeek
                ];

            case 'single':
            case 'monthly':
            case 'quarterly':
            case 'bimonthly':
            case 'semiannual':

                return [
                    'frequency' => $frequency,
                    'day' => $billingDate->day
                ];

            case 'annually':

                return [
                    'frequency' => $frequency,
                    'day' => $billingDate->day,
                    'month' => $billingDate->month
                ];

            default:
                throw new BadRequestException('The selected payment schedule frequency is invalid.');
        }
    }
}
