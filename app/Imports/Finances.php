<?php

namespace App\Imports;

use App\Models\Merchant;
use App\Models\MerchantFinance;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class Finances implements SkipsEmptyRows, ToModel, WithBatchInserts, WithHeadingRow, WithValidation
{
    use Importable;

        /**
     * The valid date formats.
     *
     * @var array
     */
    protected $dateFormats = [
        'Y-m-d',
        'Y-m-d',
        'Y-m-d',
        'd/m/Y',
        'd/m/y',
        'm/d/y',
        'n/d/y',
        'n/t/Y',
        'n/d/Y',
        'n/j/Y',
    ];

    /**
    * @param  array  $row
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row): ?Model
    {
        $row = [
            'merchant_id' => $row['merchant_id'],
            'no_of_payments' => $row['no_of_payments'],
            'total_value' => $row['total_value'],
            'remittance_date' => $this->getDateString($row['remittance_date']),
            'google_link' => filter_var($row['google_link'], FILTER_VALIDATE_URL) ? $row['google_link'] : null
        ];


        $merchant = Merchant::find($row['merchant_id']);

        if (!$merchant) {
            return null;
        }

        return new MerchantFinance($row);
    }

    /**
     * @return int
     */
    public function batchSize(): int
    {
        return 1000;
    }

    /**
    * @return array
    */
    public function rules(): array
    {
        return [
            'merchant_id' => 'required|integer|min:1',
            'no_of_payments' => 'required|numeric|min:1',
            'total_value' => 'required|numeric|min:1',
            'google_link' => 'nullable',
            'remittance_date' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (is_null($value)) return;

                    $validFormat = collect($this->dateFormats)
                        ->first(function ($format) use ($value) {
                            return is_date($value, $format);
                        });



                    if (is_null($validFormat) && !excel_date($value)) {
                        $fail(trans('validation.date', compact('attribute')));
                    }
                },
            ],
        ];
    }

    /**
     * Get the valid date string from the given date.
     *
     * @param  string  $date
     * @return string
     */
    protected function getDateString(string $date): string
    {
        $validFormat = collect($this->dateFormats)
            ->first(function ($format) use ($date) {
                return is_date($date, $format);
            });

        return excel_date($date, $validFormat ?? 'Y-m-d')->toDateString();
    }
}
