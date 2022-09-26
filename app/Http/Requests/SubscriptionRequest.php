<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $subscription = $this->route('subscription');

        if ($subscription && $this->isFromMerchant()) {
            if (is_numeric($subscription)) {
                return $this->userOrClient()->merchant->subscriptions()
                    ->whereKey($subscription)
                    ->exists();
            }

            if ($subscription instanceof Subscription) {
                return $subscription->merchant_id === $this->userOrClient()->merchant_id;
            }

            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
