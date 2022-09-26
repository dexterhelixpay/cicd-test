<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Notifications\Contracts\SendsMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class ShopifyProductsIdentificationNotification extends Notification implements SendsMail, ShouldQueue
{
    use Queueable;

    /**
     * The merchant's ID.
     *
     * @var int
     */
    public $merchantId;

    /**
     * The shopify product type
     *
     * @var string
     */
    public $type;

     /**
     * The shopify products count
     *
     * @var string
     */
    public $totalProducts;


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($merchantId, $type, $totalProducts)
    {
        $this->merchantId = $merchantId;
        $this->type = $type;
        $this->totalProducts = $totalProducts;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['sendgrid'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $products = Cache::get("merchants:{$this->merchantId}:shopify_products:identifying:formatted_products");

        $mail = (new MailMessage)
            ->subject("BukoPay - {$this->type} Shopify Products")
            ->greeting("Here's the list of {$this->type} Products")
            ->line("Total Products : {$this->totalProducts}")
            ->line("");

        $products->each(function ($product) use(&$mail) {
            $mail->line("{$product['title']} - {$product['legacyResourceId']}");
        });

        if (Cache::has("merchants:{$this->merchantId}:shopify_products:identifying:cc")) {
            $mail->cc(Cache::get("merchants:{$this->merchantId}:shopify_products:identifying:cc"));
        }

        Cache::delete("merchants:{$this->merchantId}:shopify_products");
        Cache::delete("merchants:{$this->merchantId}:shopify_products:identifying");
        Cache::delete("merchants:{$this->merchantId}:shopify_products:identifying:to");
        Cache::delete("merchants:{$this->merchantId}:shopify_products:identifying:cc");
        Cache::delete("merchants:{$this->merchantId}:shopify_products:identifying:type");
        Cache::delete("merchants:{$this->merchantId}:shopify_products:identifying:formatted_products");

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
