<?php

namespace App\Models;

use App\Libraries\Image;
use Illuminate\Support\Str;
use App\Models\RecordableModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductGroupDescriptionItem extends RecordableModel
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
     * Get the product group.
     *
     * @return \Illuminate\Database\Eloquent\Relations
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MerchantProductGroup::class, 'merchant_product_group_id');
    }

    /**
     * Upload the given image.
     *
     * @param  \Illuminate\Http\UploadedFile|string  $image
     * @param  string  $attribute
     * @return $this
     */
    public function uploadImage($image, $attribute)
    {
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $extension = $image instanceof UploadedFile
            ? $image->getClientOriginalExtension()
            : 'webp';

        $directory = "images/merchants/{$this->group->merchant->id}/product_groups/description_items";
        $callback = fn ($img) => (new Image($img))->resizeMax(80, 80);

        $path = "{$directory}/{$fileRoot}";

        if ($extension === 'gif') {
            $path .= ".{$extension}";
            Storage::put($path, $image->getContent());
        } else {
            $path .= '.webp';
            $img = isset($callback) ? $callback($image) : new Image($image);

            tap($img, fn ($img) => $img->encode('webp'))->put($path);
        }

        return $this->setAttribute($attribute, $path);
    }
}
