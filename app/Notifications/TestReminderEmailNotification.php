<?php
namespace App\Notifications;

use App\Mail\PaymentReminder;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderedProduct;
use App\Models\Subscription;
use App\Notifications\Contracts\SendsMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TestReminderEmailNotification extends Notification implements SendsMail, ShouldQueue
{
    use Queueable;

    /**
     * The subscription model.
     *
     * @var \App\Models\Subscription
     */
    public $subscription;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The ordered product model.
     *
     * @var \App\Models\OrderedProduct
     */
    public $product;

    /**
     * The order model.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * The email options.
     *
     * @param array
     */
    public $options;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function __construct(
        Subscription $subscription,
        Merchant  $merchant,
        OrderedProduct $product,
        Order $order,
        $options = []
    ) {
        $this->subscription = $subscription;
        $this->merchant = $merchant;
        $this->product = $product;
        $this->order = $order;
        $this->options = $options;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return app()->isLocal() ? ['mail'] : ['sendgrid'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new PaymentReminder(
            $this->subscription,
            $this->merchant,
            $this->product,
            $this->order,
            $this->options
        ))->subject($this->options['subject']);
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
