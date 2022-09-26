<?php

namespace App\Models;

use App\Casts\Html;
use App\Libraries\Image;
use App\Traits\TracksEmail;
use Illuminate\Support\Str;
use App\Models\Subscription;
use App\Models\RecordableModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Notifications\Order\OrderImport;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ScheduleEmail extends RecordableModel
{
    use TracksEmail;

   /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id',
        'subject',
        'headline',
        'subheader',
        'banner_url',
        'is_delivered',
        'delivered_at',
        'schedule',
        'sms_text'
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
        'is_delivered' => 'boolean'
    ];

    /**
     * Email info
     *
     * @return array
     */
    public function emailInfo()
    {
        return $this->order->subscription->getEmailInfo();
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
     * Get the subscription import.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function subscriptionImport(): HasOne
    {
        return $this->hasOne(SubscriptionImport::class);
    }

    /**
     * Get the subscriptions included in this schedule email.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
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
        $directory = "images/merchants/schedule_emails";
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
     * Send initial emails.
     *
     * @param  boolean  $isTestEmail
     * @return void
     */
    public function sendInitialEmail()
    {
        $this->subscriptions
            ->each(function(Subscription $subscription){
                $subscription->orders
                    ->each(function(Order $order) use ($subscription) {
                        $customer = $subscription->customer;
                        if (!$customer) return;

                        if (now()->startOfDay()->diffInDays($order->billing_date, false) == 0) {
                            $customer->notify(
                                (new OrderImport($order, $this->toArray()))->setChannel(['mail', 'sms', 'viber'])
                            );
                        }
                    });
            });

    }

}
