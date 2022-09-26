<?php

namespace App\Imports\ShopifyOrders;

use App\Libraries\Shopify;
use App\Models\Country;
use App\Models\PaymentStatus;
use App\Models\ProductVariant;
use App\Models\Voucher;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class Orders implements SkipsEmptyRows, ToCollection, WithCalculatedFormulas, WithHeadingRow
{
    /**
     * The merchant.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The Shopify library.
     *
     * @var \App\Libraries\Shopify
     */
    public $shopify;

    /**
     * The valid date formats.
     *
     * @var array
     */
    protected $dateFormats = [
        'Y-m-d',
        'd/m/Y',
        'd/m/y',
        'm/d/y',
        'n/d/Y',
        'n/d/y',
        'n/j/Y',
        'n/t/Y',
    ];

    /**
     * Create a new import instance.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function __construct($merchant)
    {
        $this->merchant = $merchant->load('shippingMethods');
        $this->shopify = new Shopify(
            $merchant->shopify_domain,
            $merchant->shopify_info['access_token']
        );
    }

    /**
     * @param  Collection  $collection
     * @return void
     */
    public function collection(Collection $collection)
    {
        $productVariantIds = $collection
            ->pluck('sku_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->filter()
            ->toArray();

        $response = $this->shopify->getProductsById($productVariantIds);
        $shopifyProducts = collect($response->json('data'))
            ->values()
            ->keyBy('legacyResourceId')
            ->filter();

        $response = $this->shopify->getVariantsById($productVariantIds);
        $shopifyVariants = collect($response->json('data'))
            ->values()
            ->keyBy('legacyResourceId')
            ->filter();

        $subscriptions = DB::transaction(function () use ($collection, $shopifyProducts, $shopifyVariants) {
            return $collection
                ->groupBy('name')
                ->filter(function ($items, $name) {
                    return $name !== '';
                })
                ->map(function (Collection $items) use ($shopifyProducts, $shopifyVariants) {
                    if (!$firstItem = $items->whereNotNull('id')->first()) {
                        return null;
                    }

                    $createdAt = $this->toCarbon($firstItem->get('created_at'));

                    $products = $items->map(function (Collection $item) use (
                        $shopifyProducts, $shopifyVariants, $createdAt
                    ) {
                        $data = [
                            'description' => null,
                            'images' => [],
                        ];

                        if ($variant = $shopifyVariants->get((int) $item->get('sku_id'))) {
                            $foundProduct = $this->merchant->products()
                                ->where(function ($query) use ($variant) {
                                    $query
                                        ->where('shopify_sku_id', data_get($variant, 'product.legacyResourceId'))
                                        ->orWhere('shopify_info->legacyResourceId', data_get($variant, 'product.legacyResourceId'));
                                })
                                ->latest()
                                ->first();

                            $foundVariant = ProductVariant::query()
                                ->where('product_id', optional($foundProduct)->getKey())
                                ->where('shopify_variant_id', data_get($variant, 'legacyResourceId'))
                                ->first();

                            $images = collect(data_get($variant, 'product.images.edges', []))
                                ->pluck('node.url')
                                ->toArray();

                            $data = [
                                'product_id' => optional($foundProduct)->getKey(),
                                'product_variant_id' => optional($foundVariant)->getKey(),
                                'description' => data_get($variant, 'product.descriptionHtml'),
                                'images' => $images,
                                'shopify_product_info' => [
                                    'id' => data_get($variant, 'legacyResourceId'),
                                    'variant_id' => data_get($variant, 'legacyResourceId'),
                                    'product_id' => data_get($variant, 'product.legacyResourceId'),
                                    'product_title' => data_get($variant, 'product.title'),
                                    'images' => $images,
                                ],
                            ];
                        } elseif ($product = $shopifyProducts->get((int) $item->get('sku_id'))) {
                            $foundProduct = $this->merchant->products()
                                ->where(function ($query) use ($product) {
                                    $query
                                        ->where('shopify_sku_id', data_get($product, 'legacyResourceId'))
                                        ->orWhere('shopify_info->legacyResourceId', data_get($product, 'legacyResourceId'));
                                })
                                ->latest()
                                ->first();

                            $foundVariant = ProductVariant::query()
                                ->where('product_id', optional($foundProduct)->getKey())
                                ->where('shopify_variant_id', data_get($variant, 'legacyResourceId'))
                                ->first();

                            $images = collect(data_get($product, 'images.edges', []))
                                ->pluck('node.url')
                                ->toArray();

                            $data = [
                                'product_id' => optional($foundProduct)->getKey(),
                                'product_variant_id' => optional($foundVariant)->getKey(),
                                'description' => data_get($product, 'descriptionHtml'),
                                'images' => $images,
                                'shopify_product_info' => [
                                    'id' => data_get($product, 'variants.edges.0.node.legacyResourceId'),
                                    'variant_id' => data_get($product, 'variants.edges.0.node.legacyResourceId'),
                                    'product_id' => data_get($product, 'legacyResourceId'),
                                    'product_title' => data_get($product, 'title'),
                                    'images' => $images,
                                ],
                            ];
                        }

                        if (Arr::has($data, 'shopify_product_info')) {
                            $data['shopify_product_info'] = array_merge($data['shopify_product_info'], [
                                'quantity' => intval($item->get('lineitem_quantity') ?? 1),
                                'line_price' => (float) $item->get('lineitem_price'),
                            ]);
                        }

                        return array_merge([
                            'title' => $item->get('lineitem_name_new'),
                            'payment_schedule' => [
                                'frequency' => mb_strtolower($item->get('recurrence', 'monthly')),
                                'day' => $createdAt->day,
                            ],
                            'price' => (float) $item->get('lineitem_price'),
                            'quantity' => intval($item->get('lineitem_quantity') ?? 1),
                            'are_multiple_orders_allowed' => true,
                            'is_shippable' => filter_var($item->get('lineitem_requires_shipping'), FILTER_VALIDATE_BOOL),
                        ], $data);
                    });

                    $voucher = null;

                    if ($firstItem->get('discount_type') && $firstItem->get('discount_amount')) {
                        $type = (int) $firstItem->get('discount_type');
                        $type = in_array($type, [Voucher::FIXED_TYPE, Voucher::PERCENTAGE_TYPE])
                            ? $type
                            : Voucher::FIXED_TYPE;

                        $amount = (float) $firstItem->get('discount_amount');
                        $amount = $type === Voucher::PERCENTAGE_TYPE
                            ? min($amount, 100)
                            : $amount;

                        $voucher = $this->merchant->vouchers()->create([
                            'code' => Voucher::generateCode('SHPFY'),
                            'type' => $type,
                            'amount' => $amount,
                            'total_count' => 1,
                            'remaining_count' => 1,
                        ]);
                    }

                    $subscription = [
                        'payor' => $firstItem->get('billing_name'),
                        'billing_address' => $firstItem->get('billing_address1'),
                        'billing_province' => $firstItem->get('billing_province_name'),
                        'billing_city' => $firstItem->get('billing_city'),
                        'billing_barangay' => $firstItem->get('billing_street'),
                        'billing_zip_code' => preg_replace('/[\D]/', '', $firstItem->get('billing_zip')),

                        'delivery_note' => $firstItem->get('notes'),

                        'voucher_id' => optional($voucher)->getKey(),

                        'created_at' => $createdAt->toDateTimeString(),
                        'updated_at' => $createdAt->toDateTimeString(),
                    ];

                    if ($products->where('is_shippable', true)->isNotEmpty()) {
                        $shippingMethodName = $firstItem->get('shipping_province') === 'PH-00'
                            ? 'Metro Manila Delivery'
                            : 'Province Delivery';

                        $shippingMethod = $this->merchant->shippingMethods
                            ->where('name', $shippingMethodName)
                            ->first();

                        $subscription += [
                            'shipping_method_id' => optional($shippingMethod)->getKey(),

                            'recipient' => $firstItem->get('shipping_name') ?? $firstItem->get('billing_name'),
                            'shipping_address' => $firstItem->get('shipping_address1') ?? $firstItem->get('billing_address1'),
                            'shipping_province' => $firstItem->get('shipping_province_name') ?? $firstItem->get('billing_province_name'),
                            'shipping_city' => $firstItem->get('shipping_city') ?? $firstItem->get('billing_city'),
                            'shipping_barangay' => $firstItem->get('shipping_street') ?? $firstItem->get('billing_street'),
                            'shipping_zip_code' => preg_replace('/[\D]/', '', $firstItem->get('shipping_zip') ?? $firstItem->get('billing_zip')),
                        ];
                    } else {
                        $subscription += [
                            'shipping_method_id' => null,

                            'recipient' => null,
                            'shipping_address' => null,
                            'shipping_province' => null,
                            'shipping_city' => null,
                            'shipping_barangay' => null,
                            'shipping_zip_code' => null,
                        ];
                    }

                    $customer = [
                        'name' => $firstItem->get('billing_name'),
                        'email' => $firstItem->get('email'),
                        'country_id' => Country::query()
                            ->where('code', $firstItem->get('billing_country'))
                            ->value('id'),
                        'mobile_number' => $this->getFirstValidMobileNumber([
                            $firstItem->get('phone'),
                            $firstItem->get('billing_phone'),
                            $firstItem->get('shipping_phone'),
                        ]),
                        'address' => $firstItem->get('billing_address1'),
                        'province' => $firstItem->get('billing_province_name'),
                        'city' => $firstItem->get('billing_city'),
                        'barangay' => $firstItem->get('billing_street'),
                        'zip_code' => preg_replace('/[\D]/', '', $firstItem->get('billing_zip')),
                    ];

                    ($subscription = $this->merchant->subscriptions()->make()->forceFill($subscription))
                        ->setAttribute('is_console_booking', true)
                        ->setAttribute('is_shopify_booking', true)
                        ->customer()
                        ->associate(
                            $this->merchant->customers()->create($customer)
                        )
                        ->save();

                    collect($products)
                        ->each(function ($product) use ($subscription) {
                            $subscription
                                ->products()
                                ->make($product)
                                ->setTotalPrice()
                                ->save();
                        });

                    $subscription
                        ->refresh()
                        ->setTotalPrice()
                        ->createInitialOrders();

                    /** @var \App\Models\Order */
                    $initialOrder = $subscription->initialOrder()->first();

                    if (!$initialOrder->total_price) {
                        $initialOrder
                            ->forceFill(['payment_status_id' => PaymentStatus::PAID])
                            ->update();
                    } elseif ($initialOrder->billing_date->lte(now()->startOfDay())) {
                        $subscription->notifyCustomer(
                            'payment',
                            in_array($this->merchant->getKey(), setting('CustomMerchants', []))
                        );
                    }

                    return $subscription->fresh('customer', 'products', 'orders');
                });
        });

        Log::info([
            'Generated Subscriptions From Shopify' => $subscriptions
                ->map(function ($subscription) {
                    return optional($subscription)->getKey();
                })
                ->toArray(),
        ]);
    }

    /**
     * Get the first valid mobile number.
     *
     * @param  array  $mobileNumbers
     * @return string
     */
    protected function getFirstValidMobileNumber($mobileNumbers)
    {
        return collect($mobileNumbers)
            ->filter()
            ->map(function ($mobileNumber) {
                $mobileNumber = preg_replace('/[\D]/', '', trim($mobileNumber));
                $mobileNumber = preg_replace('/^(63|0)9/', '9', $mobileNumber);

                return Str::startsWith($mobileNumber, '9')
                    ? $mobileNumber
                    : null;
            })
            ->filter()
            ->first();
    }

    /**
     * Convert the given date to a Carbon instance.
     *
     * @param  mixed  $date
     * @return \Illuminate\Support\Carbon
     */
    protected function toCarbon($date)
    {
        if (empty($date)) {
            return now()->startOfDay();
        }

        if (is_numeric($date)) {
            return Carbon::parse(Date::excelToDateTimeObject($date));
        }

        $validFormat = collect($this->dateFormats)
            ->first(function ($format) use ($date) {
                return is_date($date, $format);
            });

        if (!$validFormat) {
            return now()->startOfDay();
        }

        return Carbon::createFromFormat($validFormat, $date)->startOfDay();
    }
}
