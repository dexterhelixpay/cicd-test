<?php

namespace App\Models;

class OrderStatus extends RecordableModel
{
    /**
     * Constant representing a unpaid order.
     *
     * @var int
     */
    const UNPAID = 1;

    /**
     * Constant representing a paid order.
     *
     * @var int
     */
    const PAID = 2;

    /**
     * Constant representing a failed payment.
     *
     * @var int
     */
    const FAILED = 3;

    /**
     * Constant representing a skipped payment.
     *
     * @var int
     */
    const SKIPPED = 4;

    /**
     * Constant representing a cancelled payment.
     *
     * @var int
     */
    const CANCELLED = 5;

    /**
     * Constant representing a overdue payment.
     *
     * @var int
     */
    const OVERDUE = 6;

     /**
     * Constant representing a incomplete payment.
     *
     * @var int
     */
    const INCOMPLETE = 7;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];
}
