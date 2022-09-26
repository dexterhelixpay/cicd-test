<?php

namespace App\Models;

use App\Libraries\Image;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
class ProductTeaserCard extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'headline',
        'subheader',

        'button_text',
        'button_color',

        'video_link',
        'thumbnail_path',

        'sort_number',

        'is_visible'
    ];

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
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_visible' => 'boolean',
    ];


      /**
     * Upload the given thumnbnail
     *
     * @param  \Illuminate\Http\UploadedFile|string  $image
     * @return self
     */
    public function uploadThumbnail($image)
    {
        $directory = 'images/products/teaser_cards';
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

        return $this->setAttribute('thumbnail_path', $path);
    }

        /**
     * Get the path to the merchant's background image.
     *
     * @return string
     */
    public function getThumbnailPathAttribute($value)
    {
        return $value && !Str::contains($value, ['http', 'https'], true) ? Storage::url($value) : $value;
    }

}
