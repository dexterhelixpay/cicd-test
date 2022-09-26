<?php

namespace App\Http\Requests;

use App\Http\Requests\Contracts\WithMerchant;
use App\Models\Webhook;
use Illuminate\Validation\Rule;

class CreateWebhookRequest extends WithMerchant
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (bool) $this->user ?? $this->userOrClient();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            'url' => 'required|url',
            'events' => 'required|array',
            'events.*' => [
                'distinct',
                Rule::in(Webhook::EVENTS),
            ],
        ]);
    }
}
