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

class ShippingFee implements ShouldAutoSize, WithEvents, WithTitle
{
    use Exportable, RegistersEventListeners;

    /**
     * The merchant.
     *
     * @var \App\Models\Merchant $merchat
     */
    public static $merchant;

    /**
     * Create a new export instance.
     *
     * @var \App\Models\Merchant $merchat
     *
     * @return void
     */
    public function __construct($merchant = null)
    {
        self::$merchant = $merchant;
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

        $worksheet->getCell("A7")->setValue('Shipping Region ID');
        $worksheet->getCell("B7")->setValue('Shipping Region Name');
        $row = 8;


        $shippingMethods = self::$merchant->shippingMethods()->where('is_enabled', true)->get();

        collect($shippingMethods ?? [])
            ->each(function ($shippingMethod) use(&$worksheet, &$row) {
                $worksheet->getCell("A{$row}")
                    ->setValue($shippingMethod->id);
                $worksheet->getCell("B{$row}")
                    ->setValue($shippingMethod->name);
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
            'Product ID' => 'ID of the Product',
            'Shipping Region ID' => 'ID of the Shipping Region',
            'First Item Price' => 'Fee for the first item',
            'Additional Quantity Price' => 'Fee for the succeeding quantity'
        ];
    }

}
