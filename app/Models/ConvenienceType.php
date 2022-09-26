<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConvenienceType extends Model
{
    use HasFactory;

    /**
     * Constant representing fixed fee.
     *
     * @var int
     */
    const FIXED = 1;

    /**
     * Constant representing percentage fee.
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
