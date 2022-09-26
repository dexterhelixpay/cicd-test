<?php

namespace App\Exports;

use App\Models\Voucher;
use App\Models\OrderStatus;
use Illuminate\Support\Arr;
use App\Models\OrderedProduct;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;

class OrderSummaryExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use Exportable, RegistersEventListeners;

    /**
     * The order query.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    public $query;

    /**
     * The order headings.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    public $columns;

    /**
     * Create a new export instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function __construct($query, $columns = [])
    {
        $this->query = $query;
        $this->columns = $columns;
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
        return collect($this->columns)
            ->pluck('text')
            ->toArray();
    }

    /**
     * @param  \App\Models\Order  $order
     * @return array
     */
    public function map($order): array
    {
        $data = $this->columns->map(function($column) use ($order){
            if ($column['value'] == 'id') {
               return $order->id;
            }

            if ($column['value'] == 'billing_date') {
               return $order->billing_date
                ? Carbon::parse($order->billing_date)->format('F d, Y')
                : '';
            }

            if ($column['value'] == 'paid_at') {
               return $order->paid_at
                ? Carbon::parse($order->paid_at)->format('F d, Y')
                : '';
            }

            if ($column['value'] == 'payor') {
               return $order->payor;
            }

            if ($column['value'] == 'billing_city') {
               return $order->billing_city;
            }

            if ($column['value'] == 'shopify_order_id') {
                return $order->shopify_order_id;
            }

            if ($column['value'] == 'subscription_id') {
               return $order->subscription_id;
            }

            if ($column['value'] == 'order') {
               return $order
                ->products
                ->map(function (OrderedProduct $product) {
                    if (count($product->option_values ?? []) > 1) {
                        $optionValues = collect($product->option_values)
                            ->filter(function ($value, $key) {
                                return $value != 'Default Title' && $key != 'Frequency';
                            });

                        if (count($optionValues ?? []) < 1) {
                            return $product;
                        }

                        $product['formatted_option_values'] = $optionValues->implode('/');
                        $product->title = "{$product->title} ({$product['formatted_option_values']})";
                    }

                    return $product;
                })
                ->pluck('title')
                ->implode(', ');
            }

            if ($column['value'] == 'total_price') {
               return $order->total_price;
            }

            if ($column['value'] == 'recurrence') {
                return $order
                ->products
                ->pluck('payment_schedule.frequency')
                ->implode(', ');
            }

            if ($column['value'] == 'order_status_id') {
                switch ($order->order_status_id) {
                    case OrderStatus::UNPAID:
                        $status = 'Unpaid';
                        break;
                    case OrderStatus::PAID:
                        $status = 'Paid';
                        break;
                    case OrderStatus::FAILED:
                        $status = 'Failed';
                        break;
                    case OrderStatus::SKIPPED:
                        $status = 'Skipped';
                        break;
                    case OrderStatus::CANCELLED:
                        $status = 'Cancelled';
                        break;
                    case OrderStatus::OVERDUE:
                        $status = 'Overdue';
                        break;
                    case OrderStatus::INCOMPLETE:
                        $status = 'Incomplete';
                        break;
                    case 8: //REFUNDED
                        $status = 'Refunded';
                        break;
                }
                return $status;
            }

            if ($column['value'] == 'fulfilled_at') {
                return $order->fulfilled_at
                    ? Carbon::parse($order->fulfilled_at)->format('F d, Y')
                    : '';
            }

            if ($column['value'] == 'original_price') {
                return $order->original_price ?? '';
            }

            if ($column['value'] == 'voucher_code') {
                return $order->voucher_code ?? '';
            }

            if ($column['value'] == 'subscription.customer.mobile_number') {
                return $order->subscription
                    ->customer
                    ->load('country')
                    ->formatted_mobile_number;
            }

            if ($column['value'] == 'shipping_address') {
                return $order->shipping_address;
            }

            if ($column['value'] == 'order_number') {
                $orderIndex = $order->subscription->orders->search(function($referenceOrder) use ($order){
                    return $referenceOrder->id === $order->id;
                });
                return ordinal_number($orderIndex+1);
            }

            if ($column['value'] == 'payment_type.name') {
                return $order->paymentType
                    ? $order->paymentType->name
                    : '';
            }

            if ($column['value'] == 'subscription.customer.email') {
                return $order->subscription
                    ->customer
                    ->email;
            }

            if ($column['value'] == 'amount_before_voucher') {
                return $order->original_price ?? $order->total_price;
            }

            if ($column['value'] == 'voucher') {
                $voucher = $order->voucher;

                if (!$voucher) {
                    return '';
                }

                return $voucher->type == Voucher::FIXED_TYPE
                    ? $voucher->amount
                    : "$voucher->amount %";
            }

            if ($column['value'] == 'amount_before_voucher') {
                return $order->original_price ?? $order->total_price;
            }

            if ($column['value'] == 'total_charged') {
                return $order->paid_at
                    ? $order->total_price
                    : '';
            }

            if ($column['value'] == 'total_charged') {
                return $order->paid_at
                    ? $order->total_price
                    : '';
            }
            if ($column['value'] == 'subscription.other_info') {
                return $order->subscription->other_info
                    ? collect($order->subscription->other_info)
                        ->map(function($field) {
                            if (!$value = data_get($field, 'value')) {
                                return;
                            }
                            $label = data_get($field,'label');
                            return "{$label} : {$value}";
                        })
                        ->filter(fn($field) => $field)
                        ->implode(', ')
                    : '';
            }

            if ($column['value'] === 'recipient') {
                return $order->recipient
                    ?? $order->payor
                    ?? $order->subscription?->customer?->name;
            }
        });

        return $data->toArray();
    }

    /**
     * @param  \Maatwebsite\Excel\Events\AfterSheet  $event
     * @return void
     */
    public static function afterSheet(AfterSheet $event)
    {
        $worksheet = $event->getSheet()->getDelegate();

        $worksheet->freezePane('A2');
    }
}
