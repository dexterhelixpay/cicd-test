<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionCustomField extends RecordableModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id',
        'code',
        'label',
        'data_type',
        'dropdown_selection',
        'is_visible',
        'is_default',
        'is_required',
        'sort_number'
    ];

     /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'dropdown_selection' => 'array',
        'is_visible' => 'boolean',
        'is_default' => 'boolean',
        'is_required' => 'boolean',
    ];

    /**
     * Get the merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the custom component
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customComponent(): BelongsTo
    {
        return $this->belongsTo(CustomComponent::class);
    }
}
