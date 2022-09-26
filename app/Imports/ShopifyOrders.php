<?php

namespace App\Imports;

use App\Facades\Shopify;
use App\Models\Merchant;
use App\Models\Voucher;
use App\Support\Date;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpOfficeDate;

class ShopifyOrders implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithCalculatedFormulas, WithValidation, WithMultipleSheets
{
    use Importable;

    /**
     * The merchant.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The orders.
     *
     * @var \Illuminate\Support\Collection
     */
    public $orders;

    /**
     * The products from Shopify.
     *
     * @var \Illuminate\Support\Collection
     */
    public $shopifyProducts;

    /**
     * The product variants from Shopify.
     *
     * @var \Illuminate\Support\Collection
     */
    public $shopifyVariants;

    public function sheets(): array
    {
        return [ 0 => $this ];
    }

    /**
     * Create a new import instance.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function __construct(Merchant $merchant)
    {
        $this->merchant = $merchant;
    }

    /**
     * @param  Collection  $collection
     * @return void
     */
    public function collection(Collection $collection)
    {
        $this->orders = $collection;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            '*.sku_id' => 'required',
            '*.created_at' => 'nullable|date_format:Y-m-d',
            '*.frequency' => [
                'required',
                Rule::in([
                    'single',
                    'weekly',
                    'semimonthly',
                    'monthly',
                    'bimonthly',
                    'quarterly',
                    'semiannual',
                    'annually',
                ]),
            ],

            '*.lineitem_name' => 'required|string|max:255',
            '*.lineitem_price' => 'required|numeric|min:0',
            '*.lineitem_quantity' => 'required|integer|min:1',
            '*.lineitem_requires_shipping' => 'required|boolean',

            '*.voucher_code' => [
                'nullable',
                'string',
                Rule::exists('vouchers', 'code')
                    ->where('merchant_id', $this->merchant->getKey())
            ],

            '*.billing_name' => 'required|string|max:255',
            '*.billing_address1' => 'required|string|max:255',
            '*.billing_street' => 'required|string|max:255',
            '*.billing_city' => 'required|string|max:255',
            '*.billing_province_name' => 'required|string|max:255',
            '*.billing_zip_code' => 'required|string|max:5',
            '*.billing_country' => [
                'required',
                'string',
                'max:2',
                Rule::exists('countries', 'code'),
            ],

            '*.shipping_name' => 'nullable|string|max:255',
            '*.shipping_address1' => 'nullable|string|max:255',
            '*.shipping_street' => 'nullable|string|max:255',
            '*.shipping_city' => 'nullable|string|max:255',
            '*.shipping_province_name' => 'nullable|string|max:255',
            '*.shipping_zip_code' => 'nullable|string|max:5',
            '*.shipping_country' => [
                'nullable',
                'string',
                'max:2',
                Rule::exists('countries', 'code'),
            ],

            '*.notes' => 'nullable|string',

            '*.email' => 'required|email|max:255',
            '*.phone' => 'nullable|string|max:255',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->sometimes(
            '*.phone',
            'mobile_number',
            function ($input, $item) {
                return $item->billing_country === 'PH';
            }
        );

        $validator->sometimes(
            '*.billing_province_name',
            Rule::exists('provinces', 'name'),
            function ($input, $item) {
                return $item->billing_country === 'PH';
            }
        );

        $validator->sometimes(
            '*.shipping_province_name',
            Rule::exists('provinces', 'name'),
            function ($input, $item) {
                return $item->shipping_country === 'PH';
            }
        );

        $skuIds = collect($validator->getData())
            ->pluck('sku_id')
            ->map('intval')
            ->unique()
            ->filter()
            ->all();

        $response = Shopify::products(
            $this->merchant->shopify_domain,
            data_get($this->merchant, 'shopify_info.access_token')
        )->getById($skuIds);

        $this->shopifyProducts = collect($response->json('data'))
            ->values()
            ->keyBy('legacyResourceId')
            ->filter();

        $response = Shopify::productVariants(
            $this->merchant->shopify_domain,
            data_get($this->merchant, 'shopify_info.access_token')
        )->getById($skuIds);

        $this->shopifyVariants = collect($response->json('data'))
            ->values()
            ->keyBy('legacyResourceId')
            ->filter();

        $validator->addRules([
            '*.sku_id' => [
                'required',
                Rule::in(
                    $this->shopifyProducts->keys()->merge(
                        $this->shopifyVariants->keys()
                    )->all()
                ),
            ],
        ]);
    }

    /**
     * Prepare the row for validation.
     *
     * @param  array  $row
     * @param  int  $index
     * @return array
     */
    public function prepareForValidation($row, $index)
    {
        $row['sku_id'] = (string) ($row['sku_id'] ?? null);
        $row['frequency'] = mb_strtolower(trim($row['frequency'] ?? ''));

        if (empty($createdAt = $row['created_at'] ?? null)) {
            $row['created_at'] = now()->toDateString();
        } elseif (is_numeric($createdAt)) {
            $row['created_at'] = Carbon::parse(
                PhpOfficeDate::excelToDateTimeObject($createdAt)
            )->toDateString();
        } else {
            $row['created_at'] = Date::toCarbon($createdAt) ?? now()->toDateString();
        }

        $row['lineitem_quantity'] = intval($row['lineitem_quantity'] ?? 0);

        $row['billing_zip_code'] = (string) ($row['billing_zip_code'] ?? null);
        $row['billing_country'] = mb_strtoupper(trim($row['billing_country'] ?? ''));
        $row['shipping_zip_code'] = (string) ($row['shipping_zip_code'] ?? null);
        $row['shipping_country'] = mb_strtoupper(trim($row['shipping_country'] ?? ''));

        $row['phone'] = (string) ($row['phone'] ?? null);
        $row['phone'] = preg_replace('/[^0-9]/', '', $row['phone']);

        return $row;
    }
}
