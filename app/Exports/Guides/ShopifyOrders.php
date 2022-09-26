<?php

namespace App\Exports\Guides;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ShopifyOrders implements FromCollection, ShouldAutoSize, WithEvents, WithTitle
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
     * @param  \Maatwebsite\Excel\Events\AfterSheet  $event
     * @return void
     */
    public static function afterSheet(AfterSheet $event)
    {
        $worksheet = $event->getSheet()->getDelegate();

        $worksheet->freezePane('A2');
        $row = 2;

        collect(self::getHeaders())
            ->each(function ($header, $key) use(&$worksheet, &$row) {
                $worksheet->getCell("A{$row}")
                    ->setValue($key);
                $worksheet->getCell("B{$row}")
                    ->setValue($header);
                $row++;
            });
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Column Guide';
    }

    /**
     *
     * @return array
     */
    public static function getHeaders()
    {
        return [
            'Name' => "Customer's Name",
            'SKU ID' => 'Shopify SKU ID',
            'Created At' => 'Date created',
            'Frequency' => "Frequency type, (single, weekly, semimonthly, monthly, bimonthly, quarterly, semiannual, annually)",
            'Lineitem Name' => 'Product Name',
            'Lineitem Price' => 'Product Price',
            'Lineitem Quantity' => 'Product Quantity',
            'Lineitem Requires Shipping' => 'Shippable Product: 1, Not Shippable Product: 0',
            'Voucher Code' => "Voucher Code",
            'Billing Name' => 'Billing Name',
            'Billing Address1' => 'Billing Address',
            'Billing Street' => ' Billing Street',
            'Billing City' => 'Billing City',
            'Billing Province Name' => 'Billing Province Name',
            'Billing Zip Code' => 'Billing Zip Code',
            'Billing Country' => 'Billing Country',
            'Shipping Name' => 'Shipping Name',
            'Shipping Address1' => 'Shipping Address',
            'Shipping Street' => 'Shipping Street',
            'Shipping City' => 'Shipping City',
            'Shipping Province Name' => 'Shipping Province Name',
            'Shipping Zip Code' => 'Shipping Zip Code',
            'Shipping Country' => 'Shipping Country',
            'Notes' => 'Notes',
            'Email' => 'Email Address',
            'Phone' => 'Mobile Number',
        ];
    }

}
