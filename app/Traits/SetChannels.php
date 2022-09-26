<?php

namespace App\Traits;

trait SetChannels
{

    /**
     * The channels
     *
     * @var array
     */
    public $channels = [];

    /**
     * Set Channels
     *
     * @param array $channels
     * @return $this
     */
    public function setChannel($channels)
    {
        if (is_string($channels)) {
            switch ($channels) {
                case 'sms':
                    $this->setSMSChannel();
                    return $this;

                case 'viber':
                    $this->setViberChannel();
                    return $this;

                case 'merchantviber':
                    $this->setMerchantviberChannel();
                    return $this;

                default:
                    $this->setMailChannel();
                    return $this;
            }
        }

        collect($channels)
            ->each(function ($channel) {
                $channelName = ucfirst($channel);

                call_user_func([$this, "set{$channelName}Channel"]);
            });

        return $this;
    }

    /**
     * Set Mail Channel
     *
     * @return void
    */
    protected function setMailChannel()
    {
        $this->channels = array_merge(
            $this->channels,
            app()->isLocal() ? ['mail'] : ['sendgrid']
        );
    }

    /**
     * Set Mail Channel
     *
     * @return void
    */
    protected function setSmsChannel()
    {
        $this->channels = array_merge(
            $this->channels,
            app()->isProduction() ? ['m360'] : ['slack', 'cache']
        );
    }

    /**
     * Set Viber Channel
     *
     * @return void
    */
    protected function setViberChannel()
    {
        $this->channels = array_merge(
            $this->channels,
            ['viber']
        );
    }

    /**
     * Set Merchant Viber Channel
     *
     * @return void
    */
    protected function setMerchantviberChannel()
    {
        $this->channels = array_merge(
            $this->channels,
            ['merchantviber']
        );
    }
}
