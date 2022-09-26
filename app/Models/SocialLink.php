<?php

namespace App\Models;

use App\Libraries\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class SocialLink extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'label',
        'icon',
        'link',
        'is_visible',
        'is_footer',
        'sort_number'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_footer' => 'boolean',
        'is_visible' => 'boolean',
    ];


    /**
     * The attributes that are considered as assets.
     *
     * @var array
     */
    protected $assets = [
        'icon',
    ];

      /**
     * Upload the given thumnbnail
     *
     * @param  \Illuminate\Http\UploadedFile|string  $image
     * @return self
     */
    public function uploadIcon($image)
    {
        $directory = 'images/merchants/social_links';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $type = $image->getClientOriginalExtension();
        $path = "{$directory}/{$fileRoot}.{$type}";

        switch ($type) {
            case 'png':
            case 'jpg':
            case 'jpeg':
                $image = $image instanceof UploadedFile
                    ? $image->getRealPath()
                    : $image;

                $image = new Image($image);
                $image->encode($type);
                $image->put($path);

                break;
            case 'gif':
                $path = "{$directory}/{$fileRoot}.{$type}";

                Storage::put($path, $image->getContent());
                break;
            case 'bmp':
            case 'svg':
                $image = $image instanceof UploadedFile
                    ? $image->getContent()
                    : $image;

                Storage::put($path, $image);

                break;
        }

        return $this->setAttribute('icon', $path);
    }


     /**
     * Get the merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the path to the merchant's background image.
     *
     * @return string
     */
    public function getIconAttribute($value)
    {
        return $value && !Str::contains($value, ['http', 'https'], true) ? Storage::url($value) : $value;
    }

}
