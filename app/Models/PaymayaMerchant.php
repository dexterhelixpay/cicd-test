<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymayaMerchant extends RecordableModel
{
    use SoftDeletes;

    /**
     * Get the credentials.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function keys(): HasMany
    {
        return $this->hasMany(PaymayaMerchantKey::class);
    }
}
