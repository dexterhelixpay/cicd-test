<?php

namespace App\Models;

use App\Models\RecordableModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductProperty extends RecordableModel
{
    /**
     * Constant representing a text field type.
     *
     * @var string
     */
    const TYPE_TEXT_FIELD = 'TEXT_FIELD';

    /**
     * Constant representing a text area type.
     *
     * @var string
     */
    const TYPE_TEXT_AREA = 'TEXT_AREA';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'hint',
        'type',
        'is_enabled',
        'is_required'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'is_required' => 'boolean'
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
}
