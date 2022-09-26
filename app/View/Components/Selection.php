<?php

namespace App\View\Components;

use App\Models\Order;
use App\Support\Prices;
use App\Traits\SetEditUrl;
use App\Models\OrderStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\View\Component;
use App\Models\ConvenienceType;
use Illuminate\Support\Facades\Storage;

class Selection extends Component
{
    use SetEditUrl;
    /**
     * The selected product of the customer.
     *
     * @var array
     */
    public $product;

    /**
     * The selected products of the customer.
     *
     * @var array||object
     */
    public $orderedProducts;

    /**
     * The transaction id.
     *
     * @var int|null
     */
    public $transactionId;

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
     * The image of the product
     *
     * @var string
     */
    public $image;

    /**
     * If has edit button
     *
     * @var int
     */
    public $hasEditButton;

     /**
     * If is payment reminder
     *
     * @var int
     */
    public $isPaymentReminder;

    /**
     * current billing date
     *
     * @var string
     */
    public $billingDate;

     /**
     * Ordered Product Count
     *
     * @var int
     */
    public $productCount;

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
     * If initial order is unpaid or failed
     *
     * @var boolean
     */
    public $isUnpaidOrFailedInitialOrder;

     /**
     * If initial order and edit reminder
     *
     * @var boolean
     */
    public $isInitialAndEditReminder;

    /**
     * If button color
     *
     * @var string
     */
    public $buttonColor;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $product,
        $order,
        $transactionId = null,
        $merchant = null,
        $subscription = null,
        $type = null,
        $totalAmountLabel = 'Total Amount',
        $hasEditButton = false,
        $isPaymentReminder = false,
        $isConsoleBooking = false
    ) {
        $this->hasEditButton = $hasEditButton;
        $this->product = $product;
        $this->transactionId = $transactionId;

        $this->order = $order;
        $this->subscription = $subscription;
        $this->merchant = $merchant;
        $this->type = $type;
        $this->isConsoleBooking = $isConsoleBooking;

        $buttonColor = $this->merchant->button_background_color ?: $this->merchant->highlight_color ?: 'black';

        $this->buttonColor = strpos($buttonColor, 'linear-gradient') !== false
            ? "background-image:{$buttonColor};"
            : "color:{$buttonColor};";

        $this->isPaymentReminder = $isPaymentReminder;

        $this->isUnpaidOrFailedInitialOrder = $this->order->isInitial()
            && in_array($this->order->order_status_id, [
                OrderStatus::UNPAID,
                OrderStatus::INCOMPLETE,
                OrderStatus::FAILED,
            ]);

        $this->isInitialAndEditReminder = $type == 'edit'
            && $this->order->isInitial();

        $this->productCount = $order->products()->count() - 1;

        $this->billingDate = Carbon::parse($this->order['billing_date'])->format('F j');

        if ($product) {
            $actualProduct = $product->product()->with('images')->first();
            $image = null;
            $image = $actualProduct && $actualProduct->images->isNotEmpty()
                ? $actualProduct->images->first()->image_path
                : Storage::url('images/assets/no-image-set.jpeg');

            if ($subscription->is_shopify_booking && $product->images) {
                $image = Arr::first($product->images);

                if (is_array($image)) {
                    $image = $image['image_path'] ?? Arr::first($image);
                }
            }

            $this->image = $image;
        }

        $this->setType($this->type)
            ->setEditProductUrl(
                $this->order->id,
                $this->subscription->id,
                $this->subscription->customer->id,
                $this->isConsoleBooking,
            );

        $this->setType($this->type)
            ->setEditUrl(
                $this->order->id,
                $this->subscription->id,
                $this->subscription->customer->id,
                $this->isConsoleBooking,
                $this->isPaymentReminder
            );

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

        $this->orderedProducts = collect($order->products->load('product.images'))
            ->map(function ($product) use (&$hasDecimal) {
                $productHasDecimal = is_numeric( $product->price )
                    && floor( $product->price ) != $product->price;

                if ($productHasDecimal) {
                    $hasDecimal = true;
                }

                $product->price = $hasDecimal || $productHasDecimal
                    ? number_format(floor($product->price * 100)/100, 2, '.','')
                    : (!is_string($product->price)
                        ? number_format($product->price, 0, '',',')
                        : $product->price);

                return $product;
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
            ? number_format(floor((float) $value * 100)/100, 2, '.', ',')
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
        return view('components.selection');
    }
}
