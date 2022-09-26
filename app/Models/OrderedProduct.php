<?php

namespace App\Models;

use App\Casts\Html;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderedProduct extends RecordableModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'product_id',

        'title',
        'description',
        'images',
        'shopify_custom_links',
        'payment_schedule',

        'price',
        'quantity',
        'total_price',

        'shopify_product_info',

        'are_multiple_orders_allowed',
        'is_shippable',
        'is_membership',

        'product_variant_id',
        'option_values',
        'sku_meta_notes',

        'product_properties'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'description' => Html::class,
        'option_values' => 'array',
        'sku_meta_notes' => 'array',
        'images' => 'array',
        'shopify_custom_links' => 'array',
        'payment_schedule' => 'array',
        'shopify_product_info' => 'array',
        'are_multiple_orders_allowed' => 'boolean',
        'is_shippable' => 'boolean',
        'is_membership' => 'boolean',
        'product_properties' => 'array',
    ];

    /**
     * Set the payment schedule attribute.
     *
     * @param  array|null  $value
     * @return self
     */
    public function setPaymentScheduleAttribute($value)
    {
        if (is_array($value)) {
            ksort($value);
        }

        $this->attributes['payment_schedule'] = $value ? json_encode($value) : $value;

        return $this;
    }

    /**
     * Get the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

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
     * Get the related subscribed product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscribedProduct(): BelongsTo
    {
        return $this->belongsTo(SubscribedProduct::class);
    }

    /**
     * Get the selected variant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function selectedVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get the product variant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Set the total price based on the product price and quantity.
     *
     * @return self
     */
    public function setTotalPrice()
    {
        $this->total_price = $this->price
            ? $this->price * $this->quantity
            : null;

        return $this;
    }
}
