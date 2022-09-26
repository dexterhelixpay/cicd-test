<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SocialLinkIcon extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'path'
    ];

    /**
     * Get the image path.
     *
     * @return string
     */
    public function getPathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }
}
