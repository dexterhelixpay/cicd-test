<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use Illuminate\Support\Arr;
class CustomerSummaryExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use Exportable, RegistersEventListeners;

    /**
     * The customer query.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    public $query;

    /**
     * Create a new export instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        $mainHeading = [
            'Customer ID',
            'Name',
            'Email',
            'Mobile Number'
        ];

        $customFields = $this->query()
            ->first()
            ->merchant
            ->customFields
            ->map(function ($field) {
                return $field->label;
            })
            ->toArray();

        return array_merge($mainHeading, $customFields);
    }

    /**
     * @param  \App\Models\Customer  $customer
     * @return array
     */
    public function map($customer): array
    {
        $other_info =  $customer->other_info ?? [];

        $customFields = $customer->merchant->customFields
            ->map(function ($field) use ($other_info) {
                return Arr::has($other_info, $field->code)
                    ? $other_info[$field->code]
                    : '';
            })
            ->toArray();

        $mainFields = [
            $customer->getKey(),
            $customer->name,
            $customer->email,
            $customer->mobile_number,
        ];

        return array_merge($mainFields, $customFields);
    }

    /**
     * @param  \Maatwebsite\Excel\Events\AfterSheet  $event
     * @return void
     */
    public static function afterSheet(AfterSheet $event)
    {
        $worksheet = $event->getSheet()->getDelegate();

        $worksheet->freezePane('A2');
        $worksheet->getProtection()->setSheet(true);

        $row = 2;

        while ($worksheet->getCell("A{$row}")->getValue()) {
            $worksheet->getStyle("F{$row}:G{$row}")
                ->getProtection()
                ->setLocked(Protection::PROTECTION_UNPROTECTED);
            $row++;
        }

        $worksheet->getCell("A{$row}")->detach();
    }
}
