<?php

namespace App\View\Components;

use App\Models\Voucher;
use App\Models\PaymentType;
use Illuminate\View\Component;
use App\Support\ConvenienceFee;
use Illuminate\Support\Facades\Storage;

class InvoicePriceTable extends Component
{
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
     * The products
     *
     * @var array||object
     */
    public $products;

    /**
     * The subtotal fee
     *
     * @var string
     */
    public $subtotal;

    /**
     * The shipping fee
     *
     * @var string
     */
    public $shippingFee;

    /**
     * The convenience fee
     *
     * @var string
     */
    public $convenienceFee;

    /**
     * The convenience label
     *
     * @var string
     */
    public $convenienceLabel;

    /**
     * The voucher amount
     *
     * @var string
     */
    public $voucherAmount;

    /**
     * The discount
     *
     * @var string
     */
    public $discount;

    /**
     * The vat
     *
     * @var string
     */
    public $vat;

    /**
     * The payment icon
     *
     * @var string
     */
    public $paymentIcon;

    /**
     * The total price
     *
     * @var string
     */
    public $totalPrice;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($subscription = null, $order = null, $merchant = null)
    {
        $this->subscription = $subscription;
        $this->order = $order;
        $this->merchant = $merchant;
        $this->products = $order->products;
        $this->convenienceFee = 0;
        $this->discount = 0;
        $this->vat = 0;
        $this->computeOrderPriceDetails();
        $this->paymentIcon = $this->getPaymentTypeIcon();
    }

    public function getPaymentTypeIcon() {

        switch ((int) $this->order->payment_type_id) {

            case PaymentType::GCASH:
                return Storage::url("/images/payment_types/gcash.png");
                break;
            case PaymentType::GRABPAY:
                return Storage::url("/images/payment_types/grabpay.png");
                break;
            case PaymentType::CARD:
                $src = "/images/card_types/undetected.png";
                if ($this->order->paymaya_card_type) {
                    $src = "/images/card_types/{$this->order->paymaya_card_type}.png";
                }
                return Storage::url($src);
                break;
            case PaymentType::BANK_TRANSFER:
                return $this->order->bank
                    ? $this->order->bank->image_path
                    : Storage::url("/images/card_types/undetected.png");
                break;
            case PaymentType::PAYMAYA_WALLET:
                return Storage::url("/images/payment_types/paymaya.png");
                break;
            case PaymentType::CASH:
            default:
                return Storage::url("/images/card_types/undetected.png");
                break;
        }
    }

    public function computeOrderPriceDetails() {
        $this->subtotal = $this->products->sum('total_price') ?: 0;

        $this->shippingFee = $this->order->custom_shipping_fee
            ?: $this->order->shipping_fee;

        if (
            $this->subtotal == 0 ||
            ($this->merchant->free_delivery_threshold
            && $this->subtotal >= $this->merchant->free_delivery_threshold)
            || !are_shippable_products($this->products->pluck('product'))
        ) {
            $this->shippingFee = 0;
        }

        $totalPrice = $this->subtotal + $this->shippingFee;

        if ($voucher = $this->order->voucher()->first()) {
            $discount = $voucher->computeTotalDiscount([
                'totalPrice' => $totalPrice,
                'products' => $this->products,
                'customer' => $this->order->subscription->customer,
                'order' => $this->order
            ]);

            $this->voucherAmount -= data_get($discount, 'discount_amount');
            $totalPrice -= $this->voucherAmount;
        }

        if (
            $this->order->payment_type_id == PaymentType::CARD
            && $this->order->is_auto_charge
            && ($cardDiscount = $this->merchant->card_discount)
        ) {
            $originalPrice = $this->subtotal + $this->shippingFee;
            $this->discount = round($originalPrice * ($cardDiscount / 100), 2);
            $totalPrice -= $this->discount;
        }

        $convenienceDetails = ConvenienceFee::calculateOrderPrice(
            $this->merchant,
            $this->order->payment_type_id,
            optional($this->order->bank)->code ?? null,
            $totalPrice
        );

        $this->convenienceLabel = data_get($convenienceDetails, 'convenience_label_original', 'Convenience Fee');
        $totalPrice = data_get($convenienceDetails, 'total_price', $totalPrice);
        $this->convenienceFee = data_get($convenienceDetails, 'convenience_fee', 0);

        $this->vat = $this->merchant->is_vat_enabled
            ? $totalPrice * (12/ 100)
            : 0;

        $this->totalPrice = $totalPrice + $this->vat;

        if ($this->merchant->is_outstanding_balance_enabled) {
            $this->totalPrice += $this->order->previous_balance;
            $this->subtotal += $this->order->previous_balance;
        }

    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.invoice-price-table');
    }
}
