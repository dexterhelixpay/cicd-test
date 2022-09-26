<?php

namespace App\Imports;

use App\Models\Merchant;
use App\Models\v2\ProductVariant;
use App\Rules\ProductVariantExists;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeImport;

class ProductVariants implements SkipsEmptyRows, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use Importable, RegistersEventListeners;

    /**
     * Create a new import instance.
     *
     * @param  \App\Models\Merchant|null  $merchant
     * @return void
     */
    public function __construct(
        public Merchant|null $merchant
    ) {
        //
    }

    /**
     * @param  \Illuminate\Support\Collection  $collection
     * @return void
     */
    public function collection(Collection $collection)
    {
        if ($collection->isEmpty()) {
            throw ValidationException::withMessages([
                'sheet' => ['At least one variant is required.'],
            ]);
        }

        $collection->each(function (Collection $row) {
            ProductVariant::find($row->get('variant_id'))
                ->update(['stock' => $row->get('stock')]);
        });
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'variant_id' => [
                'required',
                new ProductVariantExists($this->merchant, true),
            ],
            'stock' => 'nullable|numeric|min:0|max:999999',
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            'stock.integer' => __('validation.numeric'),
        ];
    }

    /**
     * @param  \Maatwebsite\Excel\Events\BeforeImport  $event
     * @return void
     */
    public static function beforeImport(BeforeImport $event)
    {
        /** @var \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet */
        $worksheet = $event->getDelegate()->getDelegate()->getActiveSheet();

        $requiredHeaders = collect([
            'variant_id' => 'Variant ID',
            'stock' => 'Stock',
        ]);

        foreach (range('A', $worksheet->getHighestColumn(1)) as $column) {
            $header = $worksheet->getCell($column . 1)->getValue();

            if ($requiredHeaders->has($key = Str::slug($header, '_') )) {
                $requiredHeaders->forget($key);
            }
        }

        if ($requiredHeaders->isNotEmpty()) {
            $error = $requiredHeaders->count() === 2
                ? 'The selected file is invalid.'
                : 'The ' . $requiredHeaders->join(' and ')
                    . ' ' . Str::plural('header', $requiredHeaders)
                    . ' ' . Str::plural('is', $requiredHeaders) . ' required.';

            throw ValidationException::withMessages([
                'sheet' => [$error],
            ]);
        }
    }
}
