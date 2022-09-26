<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'flag',
        'code',
        'dial_code',
    ];

    /**
     * Constant representing the Philippines.
     *
     * @var string
     */
    const PHILIPPINES = 175;
}
