<?php

namespace App\Models;

use App\Casts\Html;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscribedProduct extends RecordableModel
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
        'discounted_price',
        'quantity',
        'total_price',
        'total_discounted_price',

        'are_multiple_orders_allowed',

        'is_shippable',
        'is_membership',

        'shopify_product_info',

        'product_variant_id',
        'option_values',
        'sku_meta_notes',

        'is_active_discord_member',
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
        'is_active_discord_member' => 'boolean',
        'product_properties' => 'array'
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
     * Get the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->with('images');
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
     * Get the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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
     * Set the variant based on the payment schedule.
     *
     * @return self
     */
    public function setVariantFromPaymentSchedule()
    {
        if (!$product = $this->product()->first()) {
            return $this;
        }

        $defaultVariant = $product->defaultVariant()->first();

        if (!$this->payment_schedule) {
            return $this->variant()->associate($defaultVariant);
        }

        $hasInvalidOptions = $product->options()
            ->where('code', '<>', 'recurrence')
            ->has('values', '>', 1)
            ->exists();

        if ($hasInvalidOptions) {
            return $this->variant()->associate($defaultVariant);
        }

        $variant = $product->variants()
            ->whereHas('optionValues', function ($query) {
                $query
                    ->where('value', $this->payment_schedule['frequency'])
                    ->whereHas('option', function ($query) {
                        $query->where('code', 'recurrence');
                    });
            })
            ->first();

        return $this->variant()->associate($variant ?: $defaultVariant);
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
