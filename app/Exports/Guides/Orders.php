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

class Orders implements FromCollection, ShouldAutoSize, WithEvents, WithTitle
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
            'Group ID' => 'The Group ID, If you want to have multiple products in one subscription',
            'Name' => "Customer's Name",
            'Mobile Number' => 'Mobile Number',
            'Email' => "Email Address",
            'Address' => "Address",
            'Barangay' => "Barangay",
            'City' => "City",
            'Province' => "Province",
            'Zip Code' => "Zip Code",
            'Country' => "Country Code e.g PH",
            'Payor' => "Billing Name",
            'Billing Address' => "Billing Address",
            'Billing Barangay' => "Billing Barangay",
            'Billing City' => "Billing City",
            'Billing Province' => "Billing Province",
            'Billing Zip Code' => "Billing Zip Code",
            'Billing Country' => "Country Code e.g PH",
            'Shipping Address' => "Shipping Address",
            'Shipping Barangay' => "Shipping Barangay",
            'Shipping City' => "Shipping City",
            'Shipping Province' => "Shipping Address Province",
            'Shipping Zip Code' => "Shipping Zip Code",
            'Shipping Country' => "Shipping Country",
            'Delivery Notes' => "Delivery Notes",
            'Product ID' => "Product ID, Can be found in Products Module",
            'Product Title' => "Product Title",
            'Product Description' => "Product Description",
            'Is Product Shippable?' => "Shippable Product: 1, Not Shippable Product: 0",
            'Allow Multiple Quantities?' => "Allow Multiple Product Quantities: 1, Single Order Product: 0",
            'Price' => "Product Price",
            'Quantity' => "Product Quantity",
            'Frequency' => "Frequency type, (single, weekly, semimonthly, monthly, bimonthly, quarterly, semiannual, annually)",
            'Billing Date' => "Starting Date of Subscription",
            'Voucher Code' => "Voucher Code",
            'Max Limit' => "The number of payments to be made",
        ];
    }

}
