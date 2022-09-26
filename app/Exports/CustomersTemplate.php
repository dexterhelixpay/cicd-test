<?php

namespace App\Exports;

use App\Models\Merchant;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Protection;

class CustomersTemplate implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings
{
    use Exportable, RegistersEventListeners;

    /**
     * The merchant.
     *
     * @var \App\Models\Merchant|null
     */
    public $merchant;

    /**
     * Create a new export instance.
     *
     * @param  \App\Models\Merchant|null  $merchant
     * @return void
     */
    public function __construct(?Merchant $merchant = null)
    {
        $this->merchant = optional($merchant)->load('customFields');
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return collect();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        $headings = [
            'Name',
            'Email',
            'Mobile Number',
            'Address',
            'Barangay',
            'City',
            'Province',
            'Zip Code',
            'Country',
        ];

        if ($this->merchant) {
            $headings = array_merge(
                $headings, $this->merchant->customFields->pluck('label')->toArray()
            );
        }

        return $headings;
    }

    /**
     * @param  \Maatwebsite\Excel\Events\AfterSheet  $event
     * @return void
     */
    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->getSheet()->getDelegate();
        // $spreadsheet = $event->getSheet()->getDelegate()->getParent();

        $sheet->freezePane('A2');
    }
}
