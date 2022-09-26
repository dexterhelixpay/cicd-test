<?php

namespace App\Models;

use App\Libraries\Image;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductDescriptionItem extends RecordableModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'text',
        'sort_number',
    ];

    /**
     * The attributes that are considered as assets.
     *
     * @var array
     */
    protected $assets = [
        'bullet_icon_path',
    ];

    /**
     * Get the path to the product image.
     *
     * @return string
     */
    public function getBulletIconPathAttribute($value)
    {
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
        $image = $image instanceof UploadedFile
            ? $image->getRealPath()
            : $image;

        $directory = "images/products/{$this->product_id}/description_items";
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.png";

        $image = new Image($image);
        $image->encode('png');
        $image->put($path);

        return $this->setAttribute('bullet_icon_path', $path);
    }
}
