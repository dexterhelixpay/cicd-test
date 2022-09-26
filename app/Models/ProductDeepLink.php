<?php

namespace App\Models;

use App\Casts\Html;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Ankurk91\Eloquent\Relations\BelongsToOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Magarrent\LaravelUrlShortener\Models\UrlShortener;

class ProductDeepLink extends RecordableModel
{
    use HasFactory;

    /**
     * Constant representing a KEY LENGTH
     *
     * @var integer
     */
    const KEY_LENGTH = 6;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'from_url',
        'to_url',
        'url_key',
        'product_id',
        'deep_link'
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
     * Generate shortener URL and insert into DB
     *
     * @param String $toUrl - Local or External urls
     * @return String
     */
    public static function createShortUrl(String $toUrl): String
    {
        $urlKey = self::getUniqueKey();

        $deepLink = app()->make('url')->to($urlKey);

        self::updateOrCreate([
            'to_url' => $toUrl,
        ], [
            'url_key' => $urlKey,
            'deep_link' => $deepLink
        ]);

        return $deepLink;
    }

    /**
     * Generate shortener URL
     *
     * @param String $toUrl - Local or External urls
     *
     * @return self
     */
    public function generateShortUrl(String $toUrl)
    {
        $urlKey = self::getUniqueKey();

        $deepLink = app()->make('url')->to($urlKey);

        $this->forceFill([
            'to_url' => $toUrl,
            'url_key' => $urlKey,
            'deep_link' => $deepLink
        ]);

        return $this;
    }

    /**
     * Generate a random unique key for url shortener
     *
     * @return String
     */
    protected static function getUniqueKey(): String {
        $randomKey = Str::random(self::KEY_LENGTH);

        while(self::where('url_key', $randomKey)->exists()) {
            $randomKey = Str::random(self::KEY_LENGTH);
        }

        return $randomKey;
    }

    /**
     * Get original target Url from key
     *
     * @param String $urlKey
     * @return Mixed String|Boolean
     */
    public static function getOriginalUrlFromKey(String $urlKey): Mixed {
        $url = self::where('url_key', $urlKey)->first();

        if(!$url) return false;

        return $url->to_url;
    }

}
