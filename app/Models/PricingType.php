<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingType extends RecordableModel
{
    /**
     * Constant representing fixed pricing.
     *
     * @var int
     */
    const FIXED_PRICING = 1;

    /**
     * Constant representing variable pricing.
     *
     * @var int
     */
    const VARIABLE_PRICING = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the merchants with this pricing type.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class);
    }
}
