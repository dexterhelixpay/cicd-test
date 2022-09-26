<?php

namespace App\Models;

use App\Casts\Html;
use App\Libraries\Image;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'subtitle',
        'body',
        'banner_url',
        'is_draft',
        'is_published',
        'is_enabled',
        'published_at',
        'expires_at',
    ];

    /**
     * The attributes that are considered assets.
     *
     * @var array
     */
    protected $assets = ['banner_image_path'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'body' => Html::class,
        'is_draft' => 'boolean',
        'is_published'=> 'boolean',
        'is_enabled'=> 'boolean',
    ];

    /**
     * Get the path to the banner image.
     *
     * @return string
     */
    public function getBannerImagePathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Upload the image for the email blast.
     *
     * @param  mixed  $image
     * @return $this
     */
    public function uploadImage($image)
    {
        if (!$image) return $this;

        $directory = 'images/announcements';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.{$image->getClientOriginalExtension()}";

        $image = $image instanceof UploadedFile ? $image->getContent() : $image;

        Storage::put($path, $image);

        return $this->setAttribute('banner_image_path', $path);
    }

     /**
     * Get the payment types.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function merchants(): BelongsToMany
    {
        return $this
            ->belongsToMany(Merchant::class)
            ->withPivot(['expires_at']);
    }
}
