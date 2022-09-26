<?php

namespace App\Models;

use App\Casts\Html;
use App\Libraries\Image;
use Illuminate\Support\Str;
use App\Jobs\SendEmailBlast;
use App\Traits\TracksEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MerchantEmailBlast extends Model
{
    use TracksEmail;

   /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id',

        'slug',
        'subject',
        'title',
        'subtitle',
        'body',

        'banner_url',

        'targeted_customer_ids',
        'targeted_customer_count',

        'is_draft',

        'has_limited_availability',
        'is_published',

        'published_at',

        'media_files'
    ];

    /**
     * The attributes that are considered assets.
     *
     * @var array
     */
    protected $assets = ['banner_image_path'];


    /**
     * The attributes that are considered as email type
     *
     * @var string
     */
    protected $emailType = EmailEvent::BLAST;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'body' => Html::class,
        'targeted_customer_ids' => 'array',
        'media_files' => 'array',
        'is_draft' => 'boolean',
        'is_published'=> 'boolean',
        'has_limited_availability' => 'boolean',
    ];

    /**
     * Get the merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the equivalent post.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function post(): HasOne
    {
        return $this->hasOne(Post::class, 'blast_id')->ofMany();
    }

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
     * Get the products.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this
            ->belongsToMany(Product::class, 'blast_products', 'blast_id', 'product_id')
            ->withPivot('expires_at');
    }

    /**
     * Get all the product groups included in the blast
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function productGroups(): BelongsToMany
    {
        return $this->belongsToMany(MerchantProductGroup::class, 'grouped_merchant_blasts')
            ->withPivot('grouped_merchant_blasts.expires_at');
    }

    /**
     * Get email info
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function email(): MorphOne
    {
        return $this->morphOne(Email::class, 'model');
    }

    /**
     * A voucher belongs to an blast.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * Generate an unused voucher code.
     *
     * @param  \App\Models\Customer  $customer
     * @param  string @prefix
     * @return string
     */
    public static function generateVoucherCode($customer, $prefix = null)
    {
        $prefix = mb_strtoupper(
            mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix ?: $customer->name), 0, 6)
        );

        $prefix .= mb_strtoupper(Str::random(4 - mb_strlen($prefix)));
        $number = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $code = "{$prefix}{$number}";

        if (Voucher::where('code', $code)->exists()) {
            return self::generateVoucherCode($customer);
        }

        return $code;
    }


    /**
     * Replace code to media files in the body
     *
     * @param \App\Models\Customer $customer
     *
     * @return mixed
     */
    public function replaceVoucherCode($customer)
    {
        $voucher = $this->vouchers()->where('customer_id', $customer->id)->first();

        if (!$voucher) return $this;

        $this->body = str_replace(
            '{voucherCode}',
            $voucher->code,
            $this->body
        );

        return $this;
    }

    /**
     * Replace code to media files in the body
     *
     * @return mixed
     */
    public function replaceDiscordCode()
    {
        $discordIcon = Storage::url('images/discord_white.png');

        $customerProfileUrl = config('bukopay.url.profile');

        $customerProfileUrl = "https://{$this->merchant->subdomain}.{$customerProfileUrl}";

        $link = "{$customerProfileUrl}?".http_build_query([
            'isJoiningDiscord' => true,
        ]);

        $button = "<div style='text-align:center'>
                      <a style='text-decoration: none !important' href='{$link}' target='_blank'>
                          <div style='
                              text-align: center;
                              background: #7289da;
                              padding: 10px 2px;
                              width: 70%;
                              border-radius:5px;
                              color: white !important;
                              display: table-cell'
                          >
                              <img src='${discordIcon}' style='
                              height: 18px;
                              width: 20px;
                              vertical-align: middle;
                              margin-right:10px;
                              '> Join us on Discord
                          </div>
                      </a>
                  </div>";

        $this->body = str_replace(
            '{discord_link}',
            $button,
            $this->body
        );

        return $this;
      }

    /**
     * Replace code to media files in the body
     *
     * @return mixed
     */
    public function replaceMediaFiles()
    {
        collect($this->media_files)
            ->each(function ($media) {
                $isAudio = $media['type'] == 'audio';

                $this->body = str_replace(
                    $media['code'],
                    $isAudio ? $this->formatAudio($media) : $this->formatVideo($media),
                    $this->body
                );
            });

        return $this;
    }

    /**
     * Format the video
     *
     * @param array $audio
     *
     * @return string
     */
    public function formatVideo($video)
    {
        $playButton = Storage::url('images/play-button.png');

        return "
            <a style='text-decoration:none !important' href='{$video['value']}' target='_blank'>
                <div style='
                    position: relative;
                    text-align:center !important;
                    background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url({$video['thumbnail']});
                    padding: 130px !important;
                    background-repeat: no-repeat;
                    background-size:cover;
                    background-position:center center;
                    '
                >
                    <img src='{$playButton}' style='
                        height: 70px;
                        width: 70px;
                        vertical-align: middle;
                        position: absolute !important;
                        top:40%;
                        left:45%;
                    '>
                </div>
            </a>";
    }

    /**
     * Format the audio
     *
     * @param array $audio
     *
     * @return string
     */
    public function formatAudio($audio)
    {
        $buttonColor = $this->merchant->button_background_color ?: $this->merchant->highlight_color ?: 'black';
        $highlightColor = $this->merchant->highlight_color ?: 'black';

        $audioIcon = Storage::url('images/play-button.png');

        return "<div style='text-align:center'>
                    <div style='
                        font-size: 12px;
                        line-height: 20px;
                        font-weight: 700;
                        margin-bottom: 5px;
                        color: {$highlightColor} !important'
                    >
                        {$audio['name']}
                    </div>

                    <a style='text-decoration: none !important' href='{$audio['value']}' target='_blank'>
                        <div style='
                            text-align: center;
                            background: {$buttonColor};
                            padding: 10px 2px;
                            width: 70%;
                            border-radius:5px;
                            color: white !important;
                            display: table-cell'
                        >
                            <img src='{$audioIcon}' style='
                            height: 22px;
                            width: 20px;
                            vertical-align: middle;
                            margin-right:10px;
                            '> Click to Listen
                        </div>
                    </a>
                </div>";
    }

    /**
     * Upload the image for the email blast.
     *
     * @param  mixed  $image
     * @return void
     */
    public function uploadImage($image = null)
    {
        $directory = "images/merchants/email_blasts/{$this->merchant_id}";
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.{$image->getClientOriginalExtension()}";

        switch ($image->getClientOriginalExtension()) {
            case 'gif':
            case 'bmp':
            case 'svg':
                $image = $image instanceof UploadedFile
                    ? $image->getContent()
                    : $image;

                Storage::put($path, $image);

                break;

            case 'png':
            case 'jpg':
            case 'jpeg':
            default:
                $image = $image instanceof UploadedFile
                    ? $image->getRealPath()
                    : $image;

                $image = new Image($image);
                $image->encode('png');
                $image->put($path);

                break;
        }

        return $this->setAttribute('banner_image_path', $path);
    }

    /**
     * Notify the targeted customer about he email blast.
     *
     * @param  \App\Models\MerchantEmailBlast  $emailBlast
     * @return void
     */
    public function notifyTargetedCustomers()
    {
        if (!$this->is_published || $this->is_draft) return;

        dispatch(new SendEmailBlast($this));
    }

    /**
     * Replace placeholder with customer name
     *
     * @param \App\Models\Customer $customer
     * @param \App\Models\Customer $merchant
     * @param string $part
     *
     * @return mixed
     */
    public function replacePlaceholders($merchant, $customer)
    {
        $this->subject = $this->convertKeyWords($merchant, $customer, $this->subject);
        $this->title = $this->convertKeyWords($merchant, $customer, $this->title);
        $this->subtitle = $this->convertKeyWords($merchant, $customer, $this->subtitle);
        $this->body = $this->convertKeyWords($merchant, $customer, $this->body);

        return $this;
    }

    /**
     * Replace placeholder
     *
     * @param \App\Models\Customer $customer
     * @param string $part
     *
     * @return mixed
     */
    public function convertKeyWords($merchant, $customer, $text)
    {
        $text = str_replace(
            '{customerName}', $customer?->name, $text
        );

        $text = str_replace(
            '{subscriptionTermSingular}', $merchant?->subscription_term_singular ?? 'subscription', $text
        );

        $text = str_replace(
            '{subscriptionTermPlural}', $merchant?->subscription_term_plural ?? 'subscriptions', $text
        );

        $text = str_replace(
            '{merchantName}', $merchant?->name, $text
        );

        return $text;
    }
}
