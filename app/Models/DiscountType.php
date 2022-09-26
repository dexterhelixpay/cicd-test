<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountType extends RecordableModel
{
    /**
     * Constant representing fixed discount.
     *
     * @var int
     */
    const FIXED = 1;

    /**
     * Constant representing percentage discount.
     *
     * @var int
     */
    const PERCENTAGE = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

}
