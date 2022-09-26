<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CustomComponentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->isFromUser()
            || $this->isFromMerchant();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'data.attributes.title' => 'required|string|max:255',
            'data.attributes.description' => 'nullable|string|max:255',

            'data.relationships.fields.data.*.attributes.merchant_id' => [
                Rule::exists('merchants', 'id')
                    ->whereNull('deleted_at')
            ],
            'data.relationships.fields.data.*.attributes.label' => 'required|string|max:255',
            'data.relationships.fields.data.*.attributes.data_type' => 'required|string|max:255',
            'data.relationships.fields.data.*.attributes.is_required' => 'required|boolean',
        ];
    }
}
