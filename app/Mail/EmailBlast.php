<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class EmailBlast extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The title.
     *
     * @var string
     */
    public $title;

    /**
     * The subtitle.
     *
     * @var string
     */
    public $subtitle;

    /**
     * The email body.
     *
     * @var string|null
     */
    public $body;

    /**
     * The image path of the banner.
     *
     * @var string
     */
    public $bannerImagePath;

    /**
     * The url of the banner.
     *
     * @var string
     */
    public $bannerUrl;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The customer model.
     *
     * @var \App\Models\Customer
     */
    public $customer;


    /**
     * The header bg color
     *
     * @var string
     */
    public $headerBgColor;

    /**
     * The bg color
     *
     * @var string
     */
    public $bgColor;

    /**
     * The font settings
     *
     * @var string
     */
    public $fontCss;

    /**
     * Create a new message instance.
     *
     * @param  string  $title
     * @param  string  $subtitle
     * @param  string  $bannnerImagePath
     * @param  string  $bannerUrl
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Customer  $customer
     * @param  string|null  $body
     * @return void
     */
    public function __construct(
        $title,
        $subtitle,
        $bannerImagePath,
        $bannerUrl,
        $merchant,
        $customer,
        $body = null,
        $isBlast = false
    ) {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->bannerImagePath = $bannerImagePath;
        $this->bannerUrl = $bannerUrl;
        $this->merchant = $merchant;
        $this->customer = $customer;
        $this->body = $body;
        $this->fontCss = $isBlast
            ? $this->merchant->membership_blast_font_settings
            : $this->merchant->email_font_settings;

        $headerBackgroundColor = $merchant->header_background_color ?? 'rgba(247, 250, 252, 1)';
        $backgroundColor = $merchant->background_color ?? 'rgba(247, 250, 252, 1)';

        $this->headerBgColor = strpos($headerBackgroundColor, 'linear-gradient') !== false
            ? "background-image:{$headerBackgroundColor};"
            : "background-color:{$headerBackgroundColor};";

        $this->bgColor = strpos($backgroundColor, 'linear-gradient') !== false
            ? "background-image:{$backgroundColor};"
            : "background-color:{$backgroundColor};";
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.blast');
    }
}
