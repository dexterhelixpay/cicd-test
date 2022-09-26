<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWallet extends RecordableModel
{
     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'link_id',
        'mobile_number',
        'name',
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
