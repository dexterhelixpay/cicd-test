<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class PaymayaMerchantKey extends RecordableModel
{
    use SoftDeletes;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_secret' => 'boolean',
    ];
}
