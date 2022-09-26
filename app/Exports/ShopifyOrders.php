<?php

namespace App\Exports;

use App\Exports\Guides\ShopifyOrders as ShopifyOrderGuides;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;

class ShopifyOrders implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithMultipleSheets
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
            'Name',
            'SKU ID',
            'Created At',
            'Frequency',
            'Lineitem Name',
            'Lineitem Price',
            'Lineitem Quantity',
            'Lineitem Requires Shipping',
            'Voucher Code',
            'Billing Name',
            'Billing Address1',
            'Billing Street',
            'Billing City',
            'Billing Province Name',
            'Billing Zip Code',
            'Billing Country',
            'Shipping Name',
            'Shipping Address1',
            'Shipping Street',
            'Shipping City',
            'Shipping Province Name',
            'Shipping Zip Code',
            'Shipping Country',
            'Notes',
            'Email',
            'Phone',
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
           new ShopifyOrderGuides()
        ];
    }
}

