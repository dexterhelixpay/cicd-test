<?php

namespace App\Services;

use App\Exports\CustomersTemplate;
use App\Imports\Customers;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Subscription;
use App\Support\PaymentSchedule;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerService
{
    /**
     * Create/update the given customer for the merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  string  $mobileNumber
     * @param  string|int  $country
     * @param  array<string, mixed>  $data
     * @param  array<int, array>  $metaInfo
     * @return \App\Models\Customer
     */
    public function create(
        Merchant $merchant,
        string $mobileNumber,
        string|int $country,
        array $data,
        array $metaInfo = []
    ) {
        $country = Country::query()
            ->when(is_int($country), function ($query) use ($country) {
                $query->whereKey($country);
            }, function ($query) use ($country) {
                $query->where('name', $country)->orWhere('code', $country);
            })
            ->first();

        if (! $country) {
            $country = Country::firstWhere('code', 'PH');
        }

        $mobileNumber = $country->code === 'PH'
            ? Str::mobileNumber($mobileNumber)
            : $mobileNumber;

        $customer = $merchant->customers()
            ->where('mobile_number', $mobileNumber)
            ->where('country_id', $country->getKey())
            ->firstOrNew()
            ->fill(Arr::only($data, [
                'name',
                'email',
                'address',
                'barangay',
                'city',
                'province',
                'zip_code',
            ]))
            ->setAttribute('mobile_number', $mobileNumber)
            ->country()
            ->associate($country);

        if (($metaInfo = $this->formatMetaInfo($merchant, $metaInfo))->isNotEmpty()) {
            $customer->setAttribute('other_info', $metaInfo->toArray());
        }

        $customer->save();

        return $customer;
    }

    /**
     * Create customers from the given file.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Http\UploadedFile|string  $file
     * @param  string|null  $disk
     * @return \Illuminate\Support\Collection
     */
    public function createFromFile(Merchant $merchant, $file, $disk = null)
    {
        ($import = new Customers($merchant->loadMissing('customFields')))->import($file, $disk);

        return $import->customers->map(function (Collection $row) use ($merchant) {
            $country = Country::where('code', $row->get('country'))->first();

            $mobileNumber = trim((string) $row->get('mobile_number'));
            $mobileNumber = $country->code === 'PH' ? Str::mobileNumber($mobileNumber) : $mobileNumber;

            $customer = $merchant->customers()
                ->where('mobile_number', $mobileNumber)
                ->where('country_id', $country->getKey())
                ->firstOrNew()
                ->fill(
                    $row->only([
                        'name',
                        'email',
                        'address',
                        'barangay',
                        'city',
                        'province',
                        'zip_code',
                    ])->map(function ($attribute) {
                        return trim($attribute);
                    })->toArray()
                )
                ->setAttribute('mobile_number', $mobileNumber)
                ->country()
                ->associate($country)
                ->setAttribute('country_name', $country->name);

            $customer->save();

            $customFields = $row
                ->except((new CustomersTemplate)->headings())
                ->filter(function ($value, $key) use ($merchant) {
                    return $merchant->customFields->firstWhere('code', $key);
                });

            if ($customFields->isNotEmpty()) {
                $customer->other_info = $customFields->toArray();
            }

            $customer->save();

            return $customer->fresh();
        });
    }

    /**
     * Format the given customer meta info.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  array<string, array>  $metaInfo
     * @return \Illuminate\Support\Collection
     */
    public function formatMetaInfo(Merchant $merchant, array $metaInfo)
    {
        $merchant->loadMissing('customFields');

        return collect($metaInfo)
            ->mapWithKeys(function ($info) use ($merchant) {
                if ($merchant->customFields->doesntContain('code', $info['code'] ?? null)) {
                    return [Str::random() => null];
                }

                return [$info['code'] => $info['value'] ?? null];
            })
            ->filter(function ($info) {
                return $info
                    && isset($info['value'])
                    && ! is_null($info['value'])
                    && $info['value'] !== '';
            });
    }

    /**
     * Get the customer's active subscriptions.
     *
     * @param  \App\Models\Customer  $customer
     * @param  \Closure|null  $callback
     * @return \Illuminate\Support\Collection
     */
    public function getActiveSubscriptions(Customer $customer, ?Closure $callback = null)
    {
        return $customer->subscriptions()
            ->with('orders.products', 'products')
            ->active()
            ->when($callback, fn ($query) => $callback($query))
            ->get()
            ->map(function (Subscription $subscription) {
                $activeProducts = $subscription->products
                    ->map(function ($product) use ($subscription) {
                        $lastPaidOrder = $subscription->orders
                            ->sortByDesc('billing_date')
                            ->firstWhere(function (Order $order) use ($product) {
                                return $order->order_status_id == OrderStatus::PAID
                                    && $order->products->contains('product_id', $product->product_id);
                            });

                        if (! $lastPaidOrder) {
                            return null;
                        }

                        $paymentSchedule = $lastPaidOrder->payment_schedule
                            ?? $product->payment_schedule;

                        $nextBillingDate = PaymentSchedule::getNextEstimatedBillingDate(
                            $paymentSchedule, $lastPaidOrder->billing_date
                        );

                        if (
                            data_get($paymentSchedule, 'frequency') !== 'single'
                            && $nextBillingDate->lt(now())
                        ) {
                            return null;
                        }

                        return $product->forceFill([
                            'next_billing_date' => $nextBillingDate->toDateString(),
                        ]);
                    })
                    ->filter();

                if ($activeProducts->isEmpty()) {
                    return null;
                }

                return $subscription->setRelation('products', $activeProducts);
            })
            ->filter()
            ->values();
    }
}
