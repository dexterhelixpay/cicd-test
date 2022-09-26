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

class ShippingFee implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithValidation, WithMultipleSheets
{
    use Importable;

    /**
     * The merchant.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The shipping fees.
     *
     * @var \Illuminate\Support\Collection
     */
    public $shippingFees;

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
        $this->shippingFees = $collection;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            '*.product_id' => [
                'required',
                Rule::exists('products', 'id')
                    ->where('merchant_id', $this->merchant->getKey())
                    ->withoutTrashed(),
            ],
            '*.shipping_region_id' => [
                'required',
                Rule::exists('shipping_methods', 'id')
                    ->where('merchant_id', $this->merchant->getKey())
            ],
            '*.first_item_price' => 'nullable|numeric|min:0',
            '*.additional_quantity_price' => 'nullable|numeric|min:0',
        ];
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
        $row['shipping_method_id'] = intval($row['shipping_region_id']);
        return $row;
    }
}
