<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class CustomComponent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',

        'is_default',
        'is_visible',
        'is_customer_details',
        'is_address_details',

        'sort_number'
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_default' => 'boolean',
        'is_visible' => 'boolean',
        'is_customer_details' => 'boolean',
        'is_address_details' => 'boolean',
    ];

    /**
     * Get the merchant
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the custom fields
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(SubscriptionCustomField::class)->orderBy('sort_number', 'asc');
    }

}
