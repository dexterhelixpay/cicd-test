<?php

namespace App\Imports;

use App\Models\Merchant;
use App\Support\Date;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpOfficeDate;

class Orders implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithValidation, WithMultipleSheets
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
            '*.name' => 'required|string|max:255',
            '*.mobile_number' => 'required|string|max:255',
            '*.email' => 'required|email|max:255',
            '*.address' => 'required|string|max:255',
            '*.barangay' => 'required|string|max:255',
            '*.city' => 'required|string|max:255',
            '*.province' => 'required|string|max:255',
            '*.zip_code' => 'required|string|max:5',
            '*.country' => [
                'required',
                'string',
                'max:2',
                Rule::exists('countries', 'code'),
            ],

            '*.payor' => 'nullable|string|max:255',
            '*.billing_address' => 'nullable|string|max:255',
            '*.billing_province' => 'nullable|string|max:255',
            '*.billing_city' => 'nullable|string|max:255',
            '*.billing_barangay' => 'nullable|string|max:255',
            '*.billing_zip_code' => 'nullable|string|max:5',
            '*.billing_country' => [
                'nullable',
                'string',
                'max:2',
                Rule::exists('countries', 'code'),
            ],

            '*.recipient' => 'nullable|string|max:255',
            '*.shipping_address' => 'nullable|string|max:255',
            '*.shipping_province' => 'nullable|string|max:255',
            '*.shipping_city' => 'nullable|string|max:255',
            '*.shipping_barangay' => 'nullable|string|max:255',
            '*.shipping_zip_code' => 'nullable|string|max:5',
            '*.shipping_country' => [
                'nullable',
                'string',
                'max:2',
                Rule::exists('countries', 'code'),
            ],

            '*.delivery_note' => 'nullable|string',

            '*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')
                    ->where('merchant_id', $this->merchant->getKey())
                    ->withoutTrashed(),
            ],
            '*.product_title' => [
                'required_without:*.product_id',
                'nullable',
                'string',
                'max:255',
            ],
            '*.product_description' => 'nullable|string',
            '*.is_product_shippable' => [
                'required_without:*.product_id',
                'boolean',
            ],
            '*.allow_multiple_quantities' => [
                'required_without:*.product_id',
                'boolean',
            ],
            '*.price' => 'nullable|numeric|min:0',
            '*.quantity' => 'required|integer|min:1',

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

            '*.billing_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            '*.voucher_code' => [
                'nullable',
                'string',
                Rule::exists('vouchers', 'code')
                    ->where('merchant_id', $this->merchant->getKey())
            ],
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
            '*.mobile_number',
            'mobile_number',
            function ($input, $item) {
                return $item->country === 'PH';
            }
        );

        $validator->sometimes(
            '*.province',
            Rule::exists('provinces', 'name'),
            function ($input, $item) {
                return $item->country === 'PH';
            }
        );

        $validator->sometimes(
            '*.billing_province',
            Rule::exists('provinces', 'name'),
            function ($input, $item) {
                return $item->billing_country === 'PH';
            }
        );

        $validator->sometimes(
            '*.shipping_province',
            Rule::exists('provinces', 'name'),
            function ($input, $item) {
                return $item->shipping_country === 'PH';
            }
        );
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
        $row['mobile_number'] = (string) ($row['mobile_number'] ?? null);
        $row['zip_code'] = (string) ($row['zip_code'] ?? null);
        $row['country'] = mb_strtoupper(trim($row['country'] ?? ''));
        $row['billing_zip_code'] = (string) ($row['billing_zip_code'] ?? null);
        $row['billing_country'] = mb_strtoupper(trim($row['billing_country'] ?? ''));
        $row['shipping_zip_code'] = (string) ($row['shipping_zip_code'] ?? null);
        $row['shipping_country'] = mb_strtoupper(trim($row['shipping_country'] ?? ''));

        $row['product_id'] = !empty($row['product_id']) ? intval($row['product_id']) : null;

        if (!empty($row['quantity'])) {
            $row['quantity'] = ($row['allow_multiple_quantities'] ?? true)
                ? intval($row['quantity'])
                : min(1, intval($row['quantity']));
        } else {
            $row['quantity'] = null;
        }

        $row['frequency'] = mb_strtolower(trim($row['frequency'] ?? ''));

        if (empty($startingDate = $row['billing_date'] ?? null)) {
            $row['billing_date'] = now()->toDateString();
        } elseif (is_numeric($startingDate)) {
            $row['billing_date'] = Carbon::parse(
                PhpOfficeDate::excelToDateTimeObject($startingDate)
            )->toDateString();
        } else {
            if ($validDate = strtotime($startingDate)) {
                $startingDate = date('Y-m-d', $validDate);
            }
            $row['billing_date'] = Date::toCarbon($startingDate)->toDateString() ?? now()->toDateString();
        }

        return $row;
    }
}
