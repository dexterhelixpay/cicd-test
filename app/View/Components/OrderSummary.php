<?php

namespace App\View\Components;

use App\Support\Prices;
use App\Traits\SetEditUrl;
use Illuminate\Support\Arr;
use Illuminate\View\Component;
use App\Models\ConvenienceType;

class OrderSummary extends Component
{
    use SetEditUrl;

    /**
     * The merchant
     *
     * @var array||object
     */
    public $merchant;

    /**
     * The subscription
     *
     * @var array||object
     */
    public $subscription;

    /**
     * The order
     *
     * @var array||object
     */
    public $order;

    /**
     * The button color
     *
     * @var string
     */
    public $buttonColor;

     /**
     * Subscripiton confirmation type
     *
     * @var string
     */
    public $type;

     /**
     * If console booking
     *
     * @var boolean
     */
    public $isConsoleBooking;

     /**
     * Price Breakdown
     *
     * @var array
     */
    public $priceBreakdown;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $merchant = null,
        $subscription = null,
        $order = null,
        $type = null,
        $isConsoleBooking = false,
        $totalAmountLabel = 'Total Amount'
    ) {
        $this->merchant = $merchant;
        $this->subscription = $subscription;
        $this->order = $order;
        $this->type = $type;
        $this->isConsoleBooking = $isConsoleBooking;

        $priceBreakdown = Prices::compute(
            type: 'subscribed',
            order: $this->order,
            merchant: $this->merchant,
            paymentTypeId: $this->order->payment_type_id,
            isAutoCharge: $this->order->is_auto_charge,
            bankCode: optional($this->order->bank)->code,
            isShopifyBooking: $this->subscription->is_shopify_booking,
            shippingMethodId: $this->order->shipping_method_id,
            bankFee: $this->order->bank?->fee
        );

        $hasDecimal = collect(Arr::only($priceBreakdown, [
            'shipping_fee',
            'discount_amount',
            'voucher_amount',
            'convenience_fee',
            'vat_amount'
        ]))->contains(function ($value) {
            return is_numeric( $value ) && floor( $value ) != $value;
        });

        $this->priceBreakdown = [
            'subtotal_amount'=> [
                'label' => 'Subtotal',
                'amount' => $this->formatPrice($hasDecimal, $priceBreakdown['subtotal_price']),
                'original_amount' => $priceBreakdown['subtotal_price']
              ],
            'shipping_fee'=> [
                'label' => 'Shipping Fee',
                'amount' => $priceBreakdown['shipping_fee']
                    ? $this->formatPrice($hasDecimal, $priceBreakdown['shipping_fee'])
                    : 'FREE',
              ],
            'discount_amount' => [
                'label' => $priceBreakdown['discount_label'],
                'amount' => $this->formatPrice(
                    $hasDecimal,
                    $priceBreakdown['discount_amount'],
                    true
                ),
                'type' => 'deduction',
                'original_amount' => $priceBreakdown['discount_amount'],
             ],
             'voucher_amount' => [
                'label' => 'Voucher Discount',
                'amount' => $this->formatPrice(
                    $hasDecimal,
                    $priceBreakdown['voucher_amount'],
                    true
                ),
                'type' => 'deduction',
                'original_amount' => $priceBreakdown['voucher_amount']
             ],
             'convenience_fee' => [
                'label' => $priceBreakdown['convenience_label'],
                'amount' => $this->formatPrice($hasDecimal, $priceBreakdown['convenience_fee']),
                'original_amount' => $priceBreakdown['convenience_fee']
             ],
             'bank_fee' => [
                'label' => 'Bank Fee',
                'amount' => $this->formatPrice($hasDecimal, $priceBreakdown['bank_fee']),
                'original_amount' => $priceBreakdown['bank_fee']
             ],
             'vat_amount' => [
                'label' => 'VAT (12%)',
                'amount' => $this->formatPrice($hasDecimal, $priceBreakdown['vat_amount']),
                'original_amount' => $priceBreakdown['vat_amount']
              ],
             'total_amount' => [
                'label' => $totalAmountLabel,
                'amount' => $this->formatPrice(
                        $hasDecimal,
                        (float) str_replace(',', '', $priceBreakdown['total_price'])
                    ),
                'original_amount' => (float) str_replace(',', '', $priceBreakdown['total_price'])
              ],
        ];

        if (
            !are_shippable_products($order->products->pluck('product'))
        ) {
            unset($this->priceBreakdown['shipping_fee']);
        }

        $buttonColor = $merchant->button_background_color ?? $merchant->highlight_color;

        $this->buttonColor = strpos($buttonColor, 'linear-gradient') !== false
            ? "background-image:{$buttonColor};"
            : "background-color:{$buttonColor};";

        $this->setEditUrl(
            $this->order->id,
            $this->subscription->id,
            $this->subscription->customer->id,
            $this->isConsoleBooking,
            true
        );
    }

    /**
     * Format Price
     *
     * @param bool $hasDecimal
     * @param int|float $value
     * @param bool $isDeduction
     * @return mixed
     */
    public function formatPrice($hasDecimal, $value, $isDeduction = false)
    {
        $value = $value ?: 0;
        $value = $hasDecimal
            ? number_format(floor((float) $value * 100)/100, 2)
            : number_format($value, 0, '.', ',');

        return $isDeduction
            ? "-₱{$value}"
            : "₱{$value}";
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.order-summary');
    }
}
