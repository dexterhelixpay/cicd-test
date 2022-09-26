<?php

namespace App\Traits;

use App\Models\PaymentInfoLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait LogsPaymentInfoChanges
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootLogsPaymentInfoChanges()
    {
        static::created(function (Model $model) {
            $model->logPaymentInfo();
        });

        static::updated(function (Model $model) {
            if ($model->wasChanged($model->getPaymentInfoKey())) {
                $model->logPaymentInfo();
            }
        });
    }

    /**
     * Get the payment info logs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function paymentInfoLogs(): MorphMany
    {
        return $this->morphMany(PaymentInfoLog::class, 'model');
    }

    /**
     * Get the payment info.
     *
     * @return array|null
     */
    public function getPaymentInfo()
    {
        return $this->getAttribute($this->getPaymentInfoKey());
    }

    /**
     * Get the key for the payment info.
     *
     * @return string
     */
    public function getPaymentInfoKey()
    {
        return 'payment_info';
    }

    /**
     * Log the payment info.
     *
     * @return \App\Models\PaymentInfoLog
     */
    public function logPaymentInfo()
    {
        if (is_null($paymentInfo = $this->getPaymentInfo())) {
            return;
        }

        $log = $this->paymentInfoLogs()->make();
        $log->forceFill(['payment_info' => $paymentInfo])->save();

        return $log;
    }
}
