<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use Illuminate\Foundation\Http\FormRequest;

class MerchantMidRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $merchant = $this->route('merchant');

        if ($merchant && $this->isFromMerchant()) {
            if ($merchant instanceof Merchant) {
                return $merchant->id === $this->userOrClient()->merchant_id;
            }

            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'data.*.attributes.mid' => 'required|numeric',
            'data.*.attributes.business_segment' => 'required',
            'data.*.attributes.mdr' => 'required',
            'data.*.attributes.mcc' => 'required',
            'data.*.attributes.public_key' => 'required',
            'data.*.attributes.secret_key' => 'required',
            'data.*.attributes.is_vault' => 'boolean',
            'data.*.attributes.is_pwp' => 'boolean'
        ];
    }
}
