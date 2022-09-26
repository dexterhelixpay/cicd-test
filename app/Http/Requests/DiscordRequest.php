<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DiscordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->isFromUser()
            || $this->isFromMerchant()
            || $this->isFromCustomer();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        if ($this->is('v1/discord/override')) {
            return [
                'data.attributes.subscription_id' => 'required'
            ];
        }

        return [
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.guild_id' => 'required|string'
        ];
    }
}
