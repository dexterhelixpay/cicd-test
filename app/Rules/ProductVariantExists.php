<?php

namespace App\Rules;

use App\Models\Merchant;
use App\Models\ProductVariant;
use App\Models\v2\ProductVariant as v2ProductVariant;
use Illuminate\Contracts\Validation\Rule;

class ProductVariantExists implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @param  \App\Models\Merchant|null  $merchant
     * @return void
     */
    public function __construct(
        public Merchant|null $merchant,
        public bool $useNewModel = false
    ) {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $class = $this->useNewModel ? v2ProductVariant::class : ProductVariant::class;

        return $class::query()
            ->whereKey($value)
            ->when($this->merchant, function ($query) {
                $query->whereRelation('product', 'merchant_id', $this->merchant->getKey());
            })
            ->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.exists');
    }
}
