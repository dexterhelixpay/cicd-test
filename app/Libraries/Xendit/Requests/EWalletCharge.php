<?php

namespace App\Libraries\Xendit\Requests;

use App\Facades\Xendit;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EWalletCharge extends Request
{
    /**
     * Constant representing a one time payment.
     *
     * @var string
     */
    const PAYMENT_ONE_TIME = 'ONE_TIME_PAYMENT';

    /**
     * Constant representing a recurring payment.
     *
     * @var string
     */
    const PAYMENT_TOKENIZED = 'TOKENIZED_PAYMENT';

    /**
     * Constant representing a GCash transaction.
     *
     * @var string
     */
    const CHANNEL_GCASH = 'PH_GCASH';

    /**
     * Constant representing a GrabPay transaction.
     *
     * @var string
     */
    const CHANNEL_GRABPAY = 'PH_GRABPAY';

    /**
     * Create a new eWallet charge request.
     *
     * @param  float  $amount
     * @param  string  $currency
     * @param  string  $referenceId
     * @param  string  $checkoutMethod
     * @return void
     */
    public function __construct(
        float $amount,
        string $currency,
        string $referenceId,
        string $checkoutMethod = self::PAYMENT_ONE_TIME
    ) {
        $this->data = [
            'amount' => $amount,
            'currency' => $currency,
            'reference_id' => $referenceId,
            'checkout_method' => $checkoutMethod,
        ];
    }

    /**
     * @return $this
     */
    public function gCash()
    {
        $this->data['channel_code'] = self::CHANNEL_GCASH;

        return $this;
    }

    /**
     * @return $this
     */
    public function grabPay()
    {
        $this->data['channel_code'] = self::CHANNEL_GRABPAY;

        return $this;
    }

    /**
     * @return $this
     */
    public function redirectOnSuccess($url)
    {
        data_set($this->data, 'channel_properties.success_redirect_url', $url);

        return $this;
    }

    /**
     * @return $this
     */
    public function redirectOnFailure($url)
    {
        data_set($this->data, 'channel_properties.failure_redirect_url', $url);

        return $this;
    }

    /**
     * @return $this
     */
    public function forCustomer($customerId)
    {
        $this->data['customer_id'] = $customerId;

        return $this;
    }

    /**
     * @param  string  $feeRuleId
     * @return $this
     */
    public function withFeeRule(string $feeRuleId)
    {
        $this->headers['with-fee-rule'] = $feeRuleId;
    }

    /**
     * @param  array  $metadata
     * @return $this
     */
    public function withMetadata(array $metadata)
    {
        $this->data['metadata'] = $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function validate()
    {
        $currency = $this->data['currency'] ?? Xendit::CURRENCY_PHP;
        $minAmount = $currency === Xendit::CURRENCY_PHP ? 1 : 100;

        Validator::validate($this->data, [
            'reference_id' => 'required|string',
            'currency' => [
                'required',
                Rule::in(Xendit::CURRENCY_IDR, Xendit::CURRENCY_PHP),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:' . $minAmount,
            ],
            'checkout_method' => Rule::in([self::PAYMENT_ONE_TIME, self::PAYMENT_TOKENIZED]),
            'channel_code' => [
                'required_if:checkout_method,' . self::PAYMENT_ONE_TIME,
                Rule::in(self::CHANNEL_GCASH, self::CHANNEL_GRABPAY),
            ],
            'channel_properties.success_redirect_url' => 'required|url',
            'channel_properties.failure_redirect_url' => 'required|url',
            'payment_method_id' => 'required_if:checkout_method,' . self::PAYMENT_TOKENIZED,
            'customer_id' => 'string',
            'metadata' => 'array',
        ]);
    }
}
