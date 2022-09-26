<?php

namespace App\Models;

use Altek\Eventually\Relations\MorphToMany;
use App\Libraries\Image;
use App\Services\CustomerService;
use App\Traits\HasAssets;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Rennokki\QueryCache\Traits\QueryCacheable;

class Post extends RecordableModel
{
    use HasAssets, QueryCacheable, SoftDeletes;

    /**
     * Constant representing a blog-style post
     *
     * @var string
     */
    const TYPE_BLOG = 'BLOG';

    /**
     * Constant representing a video post.
     *
     * @var string
     */
    const TYPE_VIDEO = 'VIDEO';

    /**
     * Constant representing a Vimeo video.
     *
     * @var string
     */
    const VIDEO_VIMEO = 'VIMEO';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'video_type',

        'headline',
        'subheadline',
        'description',
        'body',

        'banner_link',

        'video_id',

        'is_visible',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'video_info' => 'array',
        'is_visible' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * The attributes that are considered assets.
     *
     * @var array
     */
    protected $assets = [
        'banner_image_path',
    ];

    /**
     * Specify the amount of time to cache queries.
     * Do not specify or set it to null to disable caching.
     *
     * @var int|\DateTime
     */
    public $cacheFor = 3600;

    /**
     * Invalidate the cache automatically upon update in the database.
     *
     * @var bool
     */
    protected static $flushCacheOnUpdate = true;

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
     * Get the email blast.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function blast(): BelongsTo
    {
        return $this->belongsTo(MerchantEmailBlast::class, 'blast_id');
    }

    /**
     * Get the product groups.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function customerViews(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'model', 'post_views')
            ->withTimestamps();
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
     * Get the products.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'model', 'post_restrictions')
            ->withPivot('expires_at');
    }

    /**
     * Scope a query to only include active posts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Models\Customer|null  $customer
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query, $customer = null)
    {
        $now = $this->freshTimestamp();
        $products = null;

        if ($customer) {
            $activeSubscriptions = (new CustomerService)->getActiveSubscriptions($customer);

            $products = $activeSubscriptions
                ->pluck('products')
                ->flatten()
                ->pluck('product_id')
                ->unique()
                ->values();
        }

        return $query
            ->where('is_visible', true)
            ->where('published_at', '<=', $now->clone()->endOfDay())
            ->where(function ($query) {
                $query
                    ->whereNull('video_type')
                    ->orWhere(function ($query) {
                        $query
                            ->where('video_type', self::VIDEO_VIMEO)
                            ->where('video_info->transcode->status', 'complete');
                    });
            })
            ->whereHas('products', function ($query) use ($products, $now) {
                $query
                    ->where(function ($query) use ($now) {
                        $query
                            ->whereNull('post_restrictions.expires_at')
                            ->orWhere(
                                'post_restrictions.expires_at', '>', $now->clone()->startOfDay()
                            );
                    })
                    ->when($products, fn ($query) => $query->whereKey($products));
            })
            ->when($customer, function ($query, $customer) {
                $query->where('merchant_id', $customer->merchant_id);
            });
    }

    /**
     * Upload the given image as the post's banner.
     *
     * @param  \Illuminate\Http\UploadedFile  $image
     * @return $this
     */
    public function uploadBanner(UploadedFile $image)
    {
        $directory = 'images/posts';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}";

        $extension = $image->getClientOriginalExtension();

        if (in_array($extension, ['gif', 'svg'])) {
            Storage::put($path .= ".{$extension}", $image->getContent());
        } else {
            tap(new Image($image), fn ($img) => $img->encode('webp'))
                ->put($path .= '.webp');
        }

        return $this->setAttribute('banner_image_path', $path);
    }
}
