<?php

namespace App\Exports;

use App\Models\SubscribedProduct;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Protection;

class SubscriptionPrices implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use Exportable, RegistersEventListeners;

    /**
     * The order query.
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
        return [
            'Subscription ID',
            'Subscribed Product ID',
            'Product ID',
            'Product Title',
            'Allow Multiple Orders?',
            'Price',
            'Quantity',
            'Total Price',
        ];
    }


    /**
     * @param  \App\Models\Subscription  $subscription
     * @return array
     */
    public function map($subscription): array
    {
        return $subscription->products
            ->map(function (SubscribedProduct $product) use ($subscription) {
                return [
                    $subscription->getKey(),
                    $product->getKey(),
                    $product->product_id,
                    $product->title,
                    $product->are_multiple_orders_allowed ? 'Yes' : 'No',
                    $product->price,
                    $product->quantity,
                ];
            })
            ->toArray();
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

        while ($worksheet->getCell("A{$row}")->getValue()) {
            $worksheet->getCell("H{$row}")
                ->setValue("=F{$row}*G{$row}");

            $row++;
        }

        $worksheet->getCell("A{$row}")->detach();
    }
}
