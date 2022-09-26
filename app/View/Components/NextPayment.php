<?php

namespace App\View\Components;

use App\Models\Bank;
use App\Models\PaymentType;
use App\Traits\SetEditUrl;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Component;

class NextPayment extends Component
{
    use SetEditUrl;

    /**
     * The payment type of customer.
     *
     * @var object
     */
    public $paymentType; // NEED

    /**
     * The order of customer.
     *
     * @var array
     */
    public $order; // NEED

    /**
     * The title
     *
     * @var string
     */
    public $title;

    /**
     * The subtitle
     *
     * @var string
     */
    public $subtitle;

    /**
     * If has change button
     *
     * @var int
     */
    public $showChangeButton; // NEED

     /**
     * The payment method.
     *
     * @var string
     */
    public $paymentMethodImagePath; // NEED

    /**
     * The billing date
     *
     * @var string
     */
    public $billingDate;

    /**
     * The subscription
     *
     * @var array||object
     */
    public $subscription; // NEED

    /**
     * The subscription
     *
     * @var array||object
     */
    public $merchant;

    /**
     * If payment failed
     *
     * @var int
     */
    public $isPaymentFailed;

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
     * Email type
     *
     *  @var string
     */
    public $type; // NEED

     /**
     * If console booking
     *
     * @var boolean
     */
    public $isConsoleBooking; // NEED


    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $order,
        $showChangeButton = false,
        $isPaymentFailed = false,
        $hasEditButton = false,
        $isPaymentReminder = false,
        $isConsoleBooking = false,
        $type = ''
    ) {
        $this->order = $order;
        $this->isPaymentFailed = $isPaymentFailed;
        $this->isPaymentReminder = $isPaymentReminder;
        $this->hasEditButton = $hasEditButton;
        $this->showChangeButton = $showChangeButton;
        $this->type = $type;
        $this->$isConsoleBooking = $isConsoleBooking;

        $this->subscription = $order->subscription()->first();
        $this->paymentType = $order->paymentType()->first();
        $this->merchant = $this->subscription->merchant()->first();

        $this->setType($this->type)
            ->setEditUrl(
                $this->order->id,
                $this->subscription->id,
                $this->subscription->customer->id,
                $this->isConsoleBooking,
                $isPaymentReminder,
                $isPaymentFailed
            );

        switch ((int) optional($this->paymentType)->getKey()) {
            case PaymentType::GCASH:
                $this->paymentMethodImagePath = Storage::url('images/payment_types/gcash.webp');
                break;

            case PaymentType::GRABPAY:
                $this->paymentMethodImagePath = Storage::url('images/payment_types/grabpay.webp');
                break;

            case PaymentType::CARD:
                $this->paymentMethodImagePath = ($cardType = $this->order->paymaya_card_type)
                    ? Storage::url('images/card_types/' . $cardType . '.webp')
                    : Storage::url('images/card_types/undetected.png');

                break;

            case PaymentType::BANK_TRANSFER:
                $bankName = $this->order->bank?->name;

                $this->paymentMethodImagePath = match ($bankName) {
                    Bank::BDO => Storage::url('images/banks/bdo.webp'),
                    Bank::BPI => Storage::url('images/banks/bpi.webp'),
                    Bank::METROBANK => Storage::url('images/banks/metrobank.webp'),
                    Bank::RCBC => Storage::url('images/banks/rcbc.webp'),
                    Bank::PNB => Storage::url('images/banks/pnb.webp'),
                    Bank::UNIONBANK => Storage::url('images/banks/unionbank.webp'),
                    default => Storage::url('images/card_types/undetected.png'),
                };

                break;

            case PaymentType::PAYMAYA_WALLET:
                $this->paymentMethodImagePath = Storage::url('images/payment_types/maya.webp');
                break;

            default:
                $this->paymentMethodImagePath = Storage::url('images/card_types/undetected.png');
                break;
        }
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.next-payment');
    }
}
