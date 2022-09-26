<?php

namespace App\Exports;

use App\Models\OrderedProduct;
use App\Models\PaymentStatus;
use App\Models\OrderStatus;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Protection;

use function PHPSTORM_META\map;

class OrderDetails implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping
{
    use Exportable, RegistersEventListeners;

    /**
     * The order query.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    public $query;

     /**
     * The order query.
     *
     * @var mixed
     */
    public $customFields;

    /**
     * Create a new export instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $customFields
     *
     * @return void
     */
    public function __construct($query, $customFields = [])
    {
        $this->query = $query;
        $this->customFields = $customFields;
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
        $headings = [
            'Order ID',
            'Subscription ID',
            'Payment Date',
            'Customer Name',
            'Customer Phone Number',
            'City',
            'Shipping Address',
            'Order Details',
            'Price',
            '# of order in subscription',
            'Delivery/Fulfillment Date',
            'Payment Status',
            'Order Status',
            'Payment Method',
            'Customer email address',
            'Voucher Code',
            'Shopify Order ID',
        ];

        $headings = array_merge($headings, collect($this->customFields)
            ->map(function ($customField) {
                return $customField->label;
            })->values()->all()
        );


        return array_merge($headings, [
            'Amount before voucher',
            'Voucher amount',
            'Total amount charged'
        ]);
    }

    /**
     * @param  \App\Models\Order  $order
     * @return array
     */
    public function map($order): array
    {
        $data = [
            $order->getKey(),
            $order->subscription->id,
            Carbon::parse($order->billing_date)->format('F d, Y'),
            optional($order->subscription->customer)->name
                ?: $order->recipient
                ?: $order->payor,
            optional($order->subscription->customer)->mobile_number,
            $order->shipping_city ?: $order->billing_city,
            $order->shipping_address ?: $order->billing_address,
            collect($order->products)
                ->map(function ($product) {
                    return "{$product->title} x{$product->quantity}";
                })->join(PHP_EOL),
            collect($order->products)->sum('total_price'),
            $order->subscription->orders()
                ->where('id', '<', $order->id)
                ->count() + 1,
            $order->shipping_date
                ? Carbon::parse($order->shipping_date)->format('F d, Y')
                : null,
            $this->getPaymentStatus($order->payment_status_id),
            $this->getOrderStatus($order->order_status_id),
            optional($order->paymentType)->name,
            optional($order->subscription->customer)->email,
            $order->voucher_code,
            $order->shopify_order_id,
        ];

        $customFields = collect(
                is_string($order->subscription->other_info)
                    ? json_decode($order->subscription->other_info ?? [], true)
                    : $order->subscription->other_info
            )
            ->filter(function ($info) {
                return Arr::has($info, 'value');
            })
            ->mapWithKeys(function ($info) {
                return [$info['code'] => $info['value']];
            });

        $customFieldValues = collect($this->customFields)
            ->map(function ($customField) use($customFields) {
                return Arr::has($customFields, $customField->code)
                    ? $customFields[$customField->code]
                    : '';
            })->values()->all();

        $data = array_merge($data, $customFieldValues);

        $voucherDiscount = $order->voucher
            ? $order->voucher->getDiscount(
                collect($order->products)->sum('total_price') + ($order->shipping_fee ?? 0)
            ) : 0;

        return array_merge($data, [
            $voucherDiscount
                ? $order->original_price
                : $order->total_price,
            $voucherDiscount ?? '',
            $order->total_price
        ]);
    }

    /**
     * @param  int $paymentStatus
     * @return string
     */
    public static function getPaymentStatus($paymentStatus)
    {
        switch ($paymentStatus) {
            case PaymentStatus::NOT_INITIALIZED:
                return 'Not Initialized';

            case PaymentStatus::PENDING:
                return 'Pending';

            case PaymentStatus::CHARGED:
                return 'Charged';

            case PaymentStatus::PAID:
                return 'Paid';

            case PaymentStatus::INCOMPLETE:
                return 'Incomplete';

            default:
                return 'Failed';
        }
    }

    /**
     * @param  int $orderStatus
     * @return string
     */
    public static function getOrderStatus($orderStatus)
    {
        switch ($orderStatus) {
            case OrderStatus::UNPAID:
                return 'Unpaid';

            case OrderStatus::PAID:
                return 'Paid';

            case OrderStatus::FAILED:
                return 'Failed';

            case OrderStatus::INCOMPLETE:
                return 'Incomplete';

            case OrderStatus::SKIPPED:
                return 'Skipped';

            default:
                return 'CANCELLED';
        }
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
    }
}
