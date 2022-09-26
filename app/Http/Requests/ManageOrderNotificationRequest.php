<?php

namespace App\Http\Requests;

use App\Models\MerchantUser;
use Illuminate\Foundation\Http\FormRequest;

class ManageOrderNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$user = $this->user()) {
            return false;
        }

        return $user instanceof MerchantUser
            ? $user->merchant_id == $this->route('order_notification')->merchant_id
            : true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'subject' => 'sometimes|string|max:255',
            'headline' => 'sometimes|string|max:255',
            'subheader' => 'sometimes|string|max:255',

            'payment_button_label' => 'sometimes|string|max:255',

            'is_enabled' => 'sometimes|boolean',
        ];
    }
}
