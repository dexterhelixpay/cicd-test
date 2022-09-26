<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCard extends RecordableModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'card_token_id',
        'card_type',
        'masked_pan',
    ];

    /**
     * Get the customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Check if the card is verified.
     *
     * @return bool
     */
    public function isVerified()
    {
        return !is_null($this->verified_at);
    }


    /**
     * Scope a query to only include payable orders.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Verify the card.
     *
     * @return self
     */
    public function verify()
    {
        if ($this->isVerified()) {
            return $this;
        }

        return $this->setAttribute('verified_at', $this->freshTimestamp());
    }
}
