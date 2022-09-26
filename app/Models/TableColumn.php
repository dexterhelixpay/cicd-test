<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableColumn extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'text',
        'value',
        'width',
        'align',
        'sortable',
        'sort',
        'is_default',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'sortable' => 'boolean',
        'is_default' => 'boolean',
    ];
}
