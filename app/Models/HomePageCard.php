<?php

namespace App\Models;


use App\Libraries\Image;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HomePageCard extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'image_path',
        'is_enabled',
        'card_link',
        'sort_number',
        'restricted_merchant_ids'
    ];

    /**
     * The attributes that are considered as assets.
     *
     * @var array
     */
    protected $assets = [
        'image_path'
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'card_link' => 'array',
        'restricted_merchant_ids' => 'array'
    ];

    /**
     * Get the path to the merchant's home banner.
     *
     * @return string
     */
    public function getImagePathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Upload the image for the email blast.
     *
     * @param  mixed  $image
     * @return void
     */
    public static function uploadImage($image = null)
    {
        $directory = "images/home_page_cards";
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.{$image->getClientOriginalExtension()}";

        switch ($image->getClientOriginalExtension()) {
            case 'png':
            case 'jpg':
            case 'jpeg':
                    $image = $image instanceof UploadedFile
                        ? $image->getRealPath()
                        : $image;

                    $image = new Image($image);
                    $image->encode('png');
                    $image->put($path);
                break;
            case 'gif':
            case 'bmp':
            case 'svg':
                $image = $image instanceof UploadedFile
                    ? $image->getContent()
                    : $image;

                Storage::put($path, $image);

                break;
        }

        return $path;
    }
}
