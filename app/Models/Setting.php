<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;
use Rennokki\QueryCache\Traits\QueryCacheable;

class Setting extends RecordableModel
{
    use QueryCacheable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'value',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'asset_keys' => 'array',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'value_type',
        'asset_keys',
    ];

    /**
     * The duration the queries will be cached.
     *
     * @var int
     */
    public $cacheFor = 43200;

    /**
     * Invalidate the cache automatically upon update in the database.
     *
     * @var bool
     */
    protected static $flushCacheOnUpdate = true;

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts()
    {
        return array_merge(parent::getCasts(), [
            'value' => $this->value_type ?? 'string',
        ]);
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'key';
    }

    /**
     * Get the value.
     *
     * @return mixed
     */
    public function getValueAttribute($value)
    {
        $value = $this->castAttribute('value', $value);

        if (!count($assetKeys = $this->asset_keys ?? [])) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($assetKeys as $key) {
                if ($value[$key] ?? null) {
                    $value[$key] = Storage::url($value[$key]);
                }
            }
        } elseif ($value && $assetKeys === ['value']) {
            $value = Storage::url($value);
        }

        return $value;
    }
}
