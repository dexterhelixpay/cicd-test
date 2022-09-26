<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginOtpLog extends Model
{
    use HasFactory;

        /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',
        'merchant_id',

        'mobile_number',
        'email',
        'is_resend',
    ];


      /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_resend' => 'boolean'
    ];
}
