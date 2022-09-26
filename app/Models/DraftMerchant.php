<?php

namespace App\Models;

use App\Models\RecordableModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftMerchant extends RecordableModel
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email',
        'mobile_number',
        'name',
        'formatted_mobile_number',
        'country_id'
    ];

    /**
     * Get the country.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
