<?php

namespace App\Models\v2;

use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\RecordableModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends RecordableModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'new_product_variants';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'stock',
        'sold',
        'is_enabled',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_default'=> 'boolean',
        'is_visible' => 'boolean',
        'is_enabled'=> 'boolean',
    ];

    /**
     * Get the option values of the variant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'new_product_variant_option_values',
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
        return $this->belongsTo(Product::class);
    }
}
