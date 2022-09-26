<?php

namespace App\Models;

use App\Casts\Html;
use App\Libraries\Image;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WelcomeEmail extends Model
{
    use HasFactory;

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
        'is_draft',
        'is_default',
        'is_published',
        'published_at',
    ];

    /**
     * The attributes that are considered assets.
     *
     * @var array
     */
    protected $assets = ['banner_image_path'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'body' => Html::class,
        'is_draft' => 'boolean',
        'is_published'=> 'boolean',
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
     * Get the products included in this welcome email.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
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
     * Upload the image for the email blast.
     *
     * @param  mixed  $image
     * @return void
     */
    public function uploadImage($image = null)
    {
        $directory = "images/merchants/welcome_email/{$this->merchant_id}";
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

        return $this->setAttribute('banner_image_path', $path);
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
