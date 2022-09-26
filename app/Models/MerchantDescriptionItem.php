<?php

namespace App\Models;

use App\Libraries\Image;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MerchantDescriptionItem extends RecordableModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'description',
        'emoji',

        'sort_number',
    ];

    /**
     * The attributes that are considered as assets.
     *
     * @var array
     */
    protected $assets = [
        'icon_path',
    ];

    /**
     * Get the path to the merchant's SVG logo.
     *
     * @return string
     */
    public function getIconPathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Upload the given icon.
     *
     * @param  \Illuminate\Http\UploadedFile  $icon
     * @return self
     */
    public function uploadIcon($icon)
    {
        $directory = "images/merchants/{$this->merchant_id}";
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.png";

        $image = new Image($icon->getRealPath());
        $image->encode('png');
        $image->put($path);

        return $this->setAttribute('icon_path', $path);
    }
}
