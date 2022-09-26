<?php

namespace App\Models;

use App\Libraries\Image;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImage extends RecordableModel
{
    use HasFactory;

     /**
     * Constant representing a photo.
     *
     * @var int
     */
    const PHOTO = 1;

    /**
     * Constant representing a video.
     *
     * @var int
     */
    const VIDEO = 2;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sort_number',
        'type',
        'video_link',
    ];

    /**
     * Get the path to the product image.
     *
     * @return string
     */
    public function getImagePathAttribute($value)
    {
        if (Str::contains($value, 'https')) return $value;

        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the path to the product image.
     *
     * @return string
     */
    public function getThumbnailImagePathAttribute($value)
    {
        if (Str::contains($value, 'https')) return $value;

        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Upload the given product image.
     *
     * @param  \Illuminate\Http\UploadedFile|string  $image
     * @return self
     */
    public function uploadImage($image)
    {
        $this->uploadThumbnailImage($image);

        $directory = "images/products/{$this->product_id}";
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
                Storage::put($path, $image->getContent());
                break;
        }

        return $this->setAttribute('image_path', $path);
    }

    /**
     * Upload the given product thumbnail image.
     *
     * @param  \Illuminate\Http\UploadedFile|string  $image
     * @return self
     */
    public function uploadThumbnailImage($image)
    {
        $image = $image instanceof UploadedFile
            ? $image->getRealPath()
            : $image;

        $directory = "images/products/thumbnails/{$this->product_id}";
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.png";

        $image = (new Image($image))->fit(120, 120);
        $image->encode('png');
        $image->put($path);

        return $this->setAttribute('thumbnail_image_path', $path);
    }
}
