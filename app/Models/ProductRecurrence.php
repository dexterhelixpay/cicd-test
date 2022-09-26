<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductRecurrence extends RecordableModel
{
    use HasFactory;


    /**
     * Constant representing a simple pricing type.
     *
     * Single order: actual price = base price
     * Other recurrences: original price = base price, actual price = subscription price
     *
     * @var int
     */
    const SIMPLE = 1;

    /**
     * Constant representing a special pricing type.
     *
     * Single order: actual price = base price
     * Quarterly: original price = base price * 3, actual price = editable
     * Annual: original price = base price * 12, actual price = editable
     * Other recurrences: original price = base price, actual price = subscription price
     *
     * @var int
     */
    const SPECIAL = 2;


    /**
     * Constant representing a weekly pricing type.
     *
     * Single order: actual price = base price
     * Weekly: base price
     * Every Other Week: base price * 2
     * Monthly: base price * 4
     * Bimonthly: base price * 8
     * Quarterly: base price * 12
     * Semi Annual: base price * 24
     * Annual:base price * 48
     * @var int
     */
    const WEEKLY_PRICING = 3;

     /**
     * Constant representing a fixed discount
     *
     * @var int
     */
    const FIXED = 1;

    /**
     * Constant representing a percentage discount
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
        'product_id',
        'recurrence_id',

        'code',
        'price',
        'original_price',

        'discount_value',
        'discount_type_id',
        'computation_type_id',

        'is_visible',
        'sort_number',

        'name',
        'initially_discounted_order_count',
        'initially_discounted_price',
        'initial_discount_percentage',
        'initial_discount_label',
        'subheader',
        'is_discount_label_enabled',
    ];

     /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_visible' => 'boolean',
        'is_discount_label_enabled' => 'boolean',
    ];

    /**
     * Get the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the recurrence.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(MerchantRecurrence::class, 'recurrence_id');
    }

    /**
     * Get price
     *
     * @param  mixed  $price
     * @return mixed
     */
    public function getPrice($price)
    {
        if ($this->computation_type_id == static::SIMPLE) {
            return $price;
        }

        if ($this->computation_type_id == static::SPECIAL) {
            switch ($this->code) {
                case 'bimonthly':
                    return ($price * 2);

                case 'quarterly':
                    return ($price * 3);

                case 'semiannual':
                    return ($price * 6);

                case 'annually':
                    return ($price * 12);
            }
        }

        switch ($this->code) {
            case 'semimonthly':
                return ($price * 2);
            case 'monthly':
                return ($price * 4);
            case 'bimonthly':
                return ($price * 8);
            case 'quarterly':
                return ($price * 12);
            case 'semiannual':
                return ($price * 24);
            case 'annually':
                return ($price * 48);
            default:
                return $price;
        }
    }


    /**
     * Compute discount
     *
     * @param  mixed  $price
     * @return mixed
     */
    public function computeShopifyDiscount($price)
    {
        if ($this->computation_type_id == static::SIMPLE) {
           return $price * ($this->discount_value / 100);
        } else if ($this->computation_type_id == static::WEEKLY_PRICING) {
            switch ($this->code) {
                case 'weekly':
                    return $price * ($this->discount_value / 100);
                case 'semimonthly':
                    return ($price * 2) * ($this->discount_value / 100);
                case 'monthly':
                    return ($price * 4) * ($this->discount_value / 100);
                case 'bimonthly':
                    return ($price * 8) * ($this->discount_value / 100);
                case 'quarterly':
                    return ($price * 12) * ($this->discount_value / 100);
                case 'semiannual':
                    return ($price * 24) * ($this->discount_value / 100);
                case 'annually':
                    return ($price * 48) * ($this->discount_value / 100);
            }
        } else {
            switch ($this->code) {
                case 'bimonthly':
                    return ($price * 2) * ($this->discount_value / 100);

                case 'quarterly':
                    return ($price * 3) * ($this->discount_value / 100);

                case 'semiannual':
                    return ($price * 6) * ($this->discount_value / 100);

                case 'annually':
                    return ($price * 12) * ($this->discount_value / 100);

                default:
                    return $price * ($this->discount_value / 100);
            }
        }
    }
}
