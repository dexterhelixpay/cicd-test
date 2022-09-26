<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Province extends RecordableModel
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'alt_names',

        'is_enabled',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'alt_names' => 'array',
        'is_enabled' => 'boolean',
    ];
}
