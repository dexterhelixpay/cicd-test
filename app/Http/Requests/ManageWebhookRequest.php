<?php

namespace App\Http\Requests;

use App\Models\MerchantUser;
use Illuminate\Foundation\Http\FormRequest;

class ManageWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$user = $this->userOrClient()) {
            return false;
        }

        return $user instanceof MerchantUser
            ? $user->merchant_id == $this->route('webhook')->merchant_id
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
            //
        ];
    }
}
