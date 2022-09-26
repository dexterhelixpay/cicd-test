<?php

namespace App\Exports;

use App\Exports\Guides\ShippingFee as ShippingFeeGuide;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class ShippingFee implements ShouldAutoSize, WithEvents, WithHeadings, WithMultipleSheets
{
    use Exportable, RegistersEventListeners;

    /**
     * The merchant.
     *
     * @var \App\Models\Merchant $merchat
     */
    public $merchant;

    /**
     * Create a new export instance.
     *
     * @var \App\Models\Merchant $merchat
     *
     * @return void
     */
    public function __construct($merchant = null)
    {
        $this->merchant = $merchant;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Product ID',
            'Shipping Region ID',
            'First Item Price',
            'Additional Quantity Price',
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
           new ShippingFeeGuide($this->merchant)
        ];
    }
}
