<?php

namespace App\Models;

class PaymentStatus extends RecordableModel
{
    /**
     * Constant representing a non-initialized payment.
     *
     * @var int
     */
    const NOT_INITIALIZED = 1;

    /**
     * Constant representing a pending payment.
     *
     * @var int
     */
    const PENDING = 2;

    /**
     * Constant representing a charged payment.
     *
     * @var int
     */
    const CHARGED = 3;

    /**
     * Constant representing a completed payment.
     *
     * @var int
     */
    const PAID = 4;

    /**
     * Constant representing a failed payment.
     *
     * @var int
     */
    const FAILED = 5;

    /**
     * Constant representing a incomplete payment.
     *
     * @var int
     */
    const INCOMPLETE = 6;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];
}
