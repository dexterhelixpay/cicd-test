<?php

namespace App\Models;

use App\Services\ProductService;
use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends RecordableModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'sku',
        'original_price',
        'price',

        'stock_count',
        'sold_count',

        'initially_discounted_order_count',
        'initially_discounted_price',
        'initial_discount_label',
        'subheader',

        'is_enabled',
        'is_default',
        'is_discount_label_enabled',

        'shopify_variant_id',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'discount_percentage',
        'formatted_subheader',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
        'is_discount_label_enabled' => 'boolean',
        'is_shippable' => 'boolean',
    ];

      /**
     * Get the discount percentage.
     *
     * @return string|null
     */
    public function getFormattedSubheaderAttribute()
    {
        if (!$this->subheader) return null;

        $subheader = $this->subheader;

        $subheader = str_replace(
            '{originalPrice}', '₱'.number_format($this->original_price, 2), $subheader
        );

        $subheader = str_replace(
            '{actualPrice}', '₱'.number_format($this->price, 2), $subheader
        );

        $subheader = str_replace(
            '{discountPercentage}', $this->discount_percentage.'%', $subheader
        );

        return $subheader;
    }

    /**
     * Get the discount percentage.
     *
     * @return int|null
     */
    public function getDiscountPercentageAttribute()
    {
        if (!$this->original_price) {
            return null;
        }

        return floor(
            ($this->original_price - ($this->price ?: 0)) / $this->original_price * 100
        );
    }

    /**
     * Get the option values of the variant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'product_variant_option_values',
            'product_variant_id',
            'product_option_value_id'
        );
    }

    /**
     * Get the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * Scope a query to only variants with the given frequency.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $frequency
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithFrequency($query, $frequency)
    {
        return $query
            ->whereHas('optionValues', function ($query) use ($frequency) {
                $query
                    ->where('value', $frequency)
                    ->whereHas('option', function ($query) {
                        $query->where('code', 'recurrence');
                    });
            });
    }

    /**
     * Map the option values.
     *
     * @return array|null
     */
    public function mapOptionValues()
    {
        $optionValues = $this->optionValues()
            ->with('option')
            ->get()
            ->mapWithKeys(function($value) {
                return [$value->option->name => $value->name];
            });

        return $optionValues->isNotEmpty()
            ? $optionValues->toArray()
            : null;
    }

    /**
     * Get the product variant's newer equivalent.
     *
     * @param  \Closure|null  $query
     * @return \App\Models\v2\ProductVariant
     */
    public function getNewerEquivalent(?Closure $query = null)
    {
        $this->loadMissing('optionValues', 'product');

        $optionValues = $this->optionValues()
            ->whereRelation('option', 'code', '<>', 'recurrence')
            ->get();

        $matchedVariant = $this->product->newVariants()
            ->whereHas('optionValues', function ($query) use ($optionValues) {
                $query->whereKey($optionValues->modelKeys());
            }, '=', $optionValues->count())
            ->when($query, function ($query, $callback) {
                $callback($query);
            })
            ->first();

        return $matchedVariant ?? (new ProductService)->recreateVariant($this);
    }
}
