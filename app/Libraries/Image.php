<?php

namespace App\Libraries;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic;

class Image
{
    /**
     * The image instance.
     *
     * @var \Intervention\Image\Image
     */
    protected $image;

    /**
     * The encoded image.
     *
     * @var string
     */
    protected $encodedImage;

    /**
     * Create a new image instance.
     *
     * @param  mixed  $image
     */
    public function __construct($image)
    {
        $this->image = $this->getImage($image);
    }

    /**
     * Encode the image.
     *
     * @param  string|null  $type
     * @param  integer  $quality
     * @return string
     */
    public function encode($type = 'jpg', $quality = 90)
    {
        return $this->encodedImage = (string) $this->image
            ->interlace()
            ->encode($type, $quality);
    }

    /**
     * Resize to best fitting size of current size.
     *
     * @param  int  $width
     * @param  int|null  $height
     * @param  Closure|null  $callback
     * @param  string  $position
     * @return self
     */
    public function fit($width, $height = null, $callback = null, $position = 'center')
    {
        $this->image->fit($width, $height, $callback, $position);

        return $this;
    }

    /**
     * Get the image's height.
     *
     * @return int
     */
    public function getHeight()
    {
        return $this->image->getHeight();
    }

    /**
     * Get the image instance.
     *
     * @param  mixed  $image
     * @return \Intervention\Image\Image
     */
    public function getImage($image)
    {
        $imagePath = $image instanceof UploadedFile
            ? $image->getRealPath()
            : $image;

        return ImageManagerStatic::make($imagePath);
    }

    /**
     * Get the image's width.
     *
     * @return int
     */
    public function getWidth()
    {
        return $this->image->getWidth();
    }

    /**
     * Write the image to the filesystem.
     *
     * @param  string  $path
     * @param  mixed  $options
     * @return bool
     */
    public function put($path, $options = [])
    {
        $encodedImage = $this->encodedImage ?? $this->encode();

        return Storage::put($path, $encodedImage, $options);
    }

    /**
     * Resize to desired width and/or height.
     *
     * @param  int|null  $width
     * @param  int|null  $height
     * @param  Closure  $callback
     * @return $this
     */
    public function resize($width = null, $height = null, $callback = null)
    {
        $this->image->resize($width, $height, $callback);

        return $this;
    }

    /**
     * Resize to desired width and/or height if it exceeds.
     *
     * @param  int  $width
     * @param  int  $height
     * @return $this
     */
    public function resizeMax($width, $height)
    {
        $width = $this->image->getWidth() >= $this->image->getHeight()
            ? $width
            : null;

        $height = $this->image->getHeight() >= $this->image->getWidth()
            ? $height
            : null;

        return $this->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
    }
}
