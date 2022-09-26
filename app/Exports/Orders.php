<?php

namespace App\Exports;

use App\Exports\Guides\Orders as OrderGuides;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class Orders implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMultipleSheets
{
    use Exportable, RegistersEventListeners;

    /**
     * The orders.
     *
     * @var \Illuminate\Support\Collection
     */
    public $orders;

    /**
     * Create a new export instance.
     *
     * @param  \Illuminate\Support\Collection  $orders
     * @return void
     */
    public function __construct(?Collection $orders = null)
    {
        $this->orders = $orders ?? collect();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->orders;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Group ID',
            'Name',
            'Mobile Number',
            'Email',
            'Address',
            'Barangay',
            'City',
            'Province',
            'Zip Code',
            'Country',
            'Payor',
            'Billing Address',
            'Billing Barangay',
            'Billing City',
            'Billing Province',
            'Billing Zip Code',
            'Billing Country',
            'Shipping Address',
            'Shipping Barangay',
            'Shipping City',
            'Shipping Province',
            'Shipping Zip Code',
            'Shipping Country',
            'Delivery Notes',
            'Product ID',
            'Product Title',
            'Product Description',
            'Is Product Shippable?',
            'Allow Multiple Quantities?',
            'Price',
            'Quantity',
            'Frequency',
            'Billing Date',
            'Voucher Code',
            'Max Limit',
            'Product Meta Description 1',
            'Product Meta Description 2',
            'Product Meta Description 3',
        ];
    }

    /**
     * @param  \Maatwebsite\Excel\Events\AfterSheet  $event
     * @return void
     */
    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->getSheet()->getDelegate();

        $sheet->freezePane('A2');
    }


    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
           $this,
           new OrderGuides()
        ];
    }
}
