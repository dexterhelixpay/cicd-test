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

class VoucherQualifiedCustomers implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithValidation, WithMultipleSheets
{
    use Importable;

    /**
     * The shipping fees.
     *
     * @var \Illuminate\Support\Collection
     */
    public $customers;


    public function headingRow(): int
    {
        return 22;
    }

    public function sheets(): array
    {
        return [ 0 => $this ];
    }

    /**
     * Create a new import instance.
     *
     * @return void
     */
    public function __construct()
    {

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
            '*.mobile_number' => [
                'required_if:*.email,null',
            ],
            '*.email' => [
                'required_if:*.mobile_number,null',
            ],
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
        $row['mobile_number'] = explode(',', $row['mobile_number']);
        $row['email'] = explode(',', $row['email']);

        return $row;
    }
}
