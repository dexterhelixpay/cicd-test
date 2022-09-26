<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LastHttpRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'user_type',

        'token',
        'browser',
        'ip_address',
        'is_revoke',

        'request_uri',

    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'last_active',
    ];

      /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'token' => 'array',
        'is_revoke' => 'boolean'
    ];

    /**
     * Get the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get last active days in human format
     *
     * @return string
     */
    public function getLastActiveAttribute()
    {
        return $this->updated_at->diffForHumans();
    }

    /**
     * Check if the user has logged in to a new device/browser
     *
     * @return bool
     */
    public function isDifferentSession($ip, $userAgent)
    {
        $currentValues = collect($this)->only([
                'browser',
                'ip_address'
            ])->values()->all();

        $newRequestValues = collect([
            'browser' => $userAgent,
            'ip_address' => $ip,
        ])->values()->all();

        return array_diff($currentValues, $newRequestValues);
    }

}
