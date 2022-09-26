<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductOption extends RecordableModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'sort_number',
        'code',
        'subtitle'
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
     * Get the values.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class);
    }

    /**
     * Generate the option's code.
     *
     * @param  int  $count
     * @return self
     */
    public function generateCode($count = 0)
    {
        if ($this->name == 'Frequency') return;

        $suffix = $count ? " {$count}" : '';
        $code = Str::snake(trim($this->name . $suffix));

        while ($this->product->options()->where('code', $code)->exists()) {
            return $this->generateCode($count + 1);
        }

        return $this->setAttribute('code', $code);
    }
}
