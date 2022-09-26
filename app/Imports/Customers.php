<?php

namespace App\Imports;

use App\Models\Merchant;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class Customers implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    /**
     * The merchant.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The read customers.
     *
     * @var \Illuminate\Support\Collection
     */
    public $customers;

    /**
     * Create a new import instance.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function __construct(Merchant $merchant)
    {
        $this->merchant = $merchant;

        HeadingRowFormatter::default('customers');
    }

    /**
     * @param  Collection  $collection
     * @return void
     */
    public function collection(Collection $collection)
    {
        $this->customers = $collection;
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            '*.name' => 'required|string|max:255',
            '*.email' => 'required|string|email|max:255',
            '*.mobile_number' => 'required|string|max:255',
            '*.address' => 'nullable|string|max:255',
            '*.barangay' => 'nullable|string|max:255',
            '*.city' => 'nullable|string|max:255',
            '*.province' => 'nullable|string|max:255',
            '*.zip_code' => 'nullable|string|max:5',
            '*.country' => [
                'required',
                'string',
                'max:2',
                Rule::exists('countries', 'code'),
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
    }

    /**
     * Prepare the row for validation.
     *
     * @param  array  $row
     * @param  int  $index
     * @return array
     */
    public function prepareForValidation($row)
    {
        $row['mobile_number'] = (string) ($row['mobile_number'] ?? null);
        $row['zip_code'] = (string) ($row['zip_code'] ?? null);

        return $row;
    }
}
