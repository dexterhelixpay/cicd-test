<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductOptionValue extends RecordableModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'value',
        'name',
        'sort_number'
    ];

    /**
     * Get the product option.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    /**
     * Generate the option's code.
     *
     * @param  int  $count
     * @return self
     */
    public function generateValue($count = 0)
    {
        $suffix = $count ? " {$count}" : '';
        $value = Str::snake(trim($this->name . $suffix));

        while ($this->option->values()->where('value', $value)->exists()) {
            return $this->generateValue($count + 1);
        }

        return $this->setAttribute('value', $value);
    }
}
