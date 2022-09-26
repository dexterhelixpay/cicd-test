<?php

namespace App\Models;

use App\Libraries\Image;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class MerchantProductGroup extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id',

        'name',
        'sort_number',
        'shopify_collection_id',
        'shopify_info',

        'is_visible',
        'is_shopify_group',

        'storefront_headline_text',
        'video_banner',
        'description_title',
        'group_banner_video_link',
        'align_banner_to_product_cards',
        'is_custom_page_design_enabled',
        'storefront_headline_css',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'shopify_info' => 'array',
        'is_visible' => 'boolean',
        'is_shopify_group' => 'boolean',
        'align_banner_to_product_cards' => 'boolean',
        'is_custom_page_design_enabled' => 'boolean',
        'storefront_headline_css' => 'array',
    ];

    /**
     * The attributes that are considered as assets.
     *
     * @var array
     */
    protected $assets = [
        'icon_path',
        'group_banner_path'
    ];

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
     * Get all posts restricted under this product group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function posts(): MorphToMany
    {
        return $this->morphToMany(Post::class, 'model', 'post_restrictions')
            ->withPivot('expires_at');
    }

    /**
     * Get all the product groups included in the blast
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function emailBlasts(): BelongsToMany
    {
        return $this->belongsToMany(MerchantEmailBlast::class, 'grouped_merchant_blasts')
            ->withPivot('grouped_merchant_blasts.expires_at');
    }

    /**
     * Get the products under this group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this
            ->belongsToMany(Product::class, 'grouped_merchant_products')
            ->withPivot('grouped_merchant_products.sort_number')
            ->orderBy('grouped_merchant_products.sort_number');
    }

    /**
     * Get the descriptions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function descriptionItems(): HasMany
    {
        return $this->hasMany(ProductGroupDescriptionItem::class);
    }

    /**
     * Get the exclusive products under this group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function exclusiveProducts(): BelongsToMany
    {
        return $this
            ->belongsToMany(Product::class, 'exclusive_product_groups');
    }

    /**
     * Get the path to the group banner.
     *
     * @return string
     */
    public function getGroupBannerPathAttribute($value)
    {
        if (Str::contains($value, 'https')) return $value;

        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the path to the group icon.
     *
     * @return string
     */
    public function getIconPathAttribute($value)
    {
        if (Str::contains($value, 'https')) return $value;

        return $value ? Storage::url($value) : null;
    }

    /**
     * Sync the group's descriptions.
     *
     * @param  array  $items
     * @return self
     */
    public function syncDescriptionItems($items)
    {
        ksort($items);

        $itemIds = collect($items)->pluck('id')->filter()->all();
        $this->descriptionItems()->whereKeyNot($itemIds)->get()->each->delete();

        collect($items)->each(function ($item, $index) {
            $descItem = $this->descriptionItems()->findOrNew(data_get($item, 'id'));
            $descItem->fill(Arr::only($item['attributes'] ?? [], ['emoji', 'description']) + [
                'sort_number' => (int) $index + 1,
            ]);

            if ($icon = data_get($item, 'attributes.icon')) {
                if (is_null($icon)) {
                    $descItem->icon_path = null;
                    $descItem->emoji = null;
                } elseif (!is_string($icon)) {
                    $descItem->uploadImage($icon, 'icon_path');
                    $descItem->emoji = null;
                }
            } elseif ($descItem->emoji) {
                $descItem->icon_path = null;
            }

            $descItem->save();
        });

        return $this;
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

        $directory = "images/merchants/{$this->merchant_id}/product_groups";
        $callback = fn ($img) => (new Image($img))->resizeMax(32, 32);

        $path = "{$directory}/{$fileRoot}";

        if ($extension === 'gif') {
            $path .= ".{$extension}";
            Storage::put($path, $image->getContent());
        } else {
            $path .= '.webp';
            $img = isset($callback) && $attribute == 'icon_path'
                ? $callback($image)
                : new Image($image);

            tap($img, fn ($img) => $img->encode('webp'))->put($path);
        }
        if ($attribute == 'group_banner_path') {
            $this->setAttribute('group_banner_video_link', null);
        }
        return $this->setAttribute($attribute, $path);
    }
}
