<?php

namespace App\Traits;

use App\Models\Email;
use App\Models\EmailEvent;

trait TracksEmail
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootTracksEmail()
    {
        static::created(function ($model) {
            $model->createEmail($model);
        });
    }

    /**
     * Create Email
     *
     * @param  bool  $force
     * @return void
     */
    public function createEmail($model)
    {
        Email::updateOrCreate([
            'model_type' => get_class($model),
            'model_id' => $model->id,
        ]);
    }

     /**
     * Get email info
     *
     * @return App\Models\Email
     */
    public function getEmailInfo()
    {
        return [
            'email_id' => optional($this->email)->id,
            'email_type' => $this->getEmailType()
        ];
    }

    /**
     * Get the email type of the model.
     *
     * @return mixed
     */
    public function getEmailType()
    {
        return $this->emailType ?? EmailEvent::BLAST;
    }
}
