<?php

namespace App\Libraries;

use App\Libraries\PayMaya\v2\Customer;
use App\Libraries\PayMaya\v2\CustomerCard;
use App\Libraries\PayMaya\v2\Payment;
use App\Libraries\PayMaya\v2\Wallet;

class PayMaya
{
    /**
     * The PayMaya API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Create a new PayMaya instance.
     *
     * @param  string  $apiUrl
     * @return void
     */
    public function __construct($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * Create a new PayMaya customer card instance.
     *
     * @param  string  $version
     * @return \App\Libraries\PayMaya\v2\CustomerCard
     */
    public function customerCards(string $version = 'v1')
    {
        return new CustomerCard(
            join('/', [$this->apiUrl, 'payments', $version, 'customers'])
        );
    }

    /**
     * Create a new PayMaya customer instance.
     *
     * @param  string  $version
     * @return \App\Libraries\PayMaya\v2\CustomerCard
     */
    public function customers(string $version = 'v1')
    {
        return new Customer(
            join('/', [$this->apiUrl, 'payments', $version])
        );
    }

    /**
     * Create a new PayMaya payment instance.
     *
     * @param  string  $version
     * @return \App\Libraries\PayMaya\v2\Payment
     */
    public function payments(string $version = 'v1')
    {
        return new Payment(
            join('/', [$this->apiUrl, 'payments', $version])
        );
    }

    /**
     * Create a new PayMaya wallet instance.
     *
     * @param  string  $version
     * @return \App\Libraries\PayMaya\v2\Payment
     */
    public function wallets(string $version = 'v2')
    {
        return new Wallet(
            join('/', [$this->apiUrl, 'payby', $version, 'paymaya'])
        );
    }
}
