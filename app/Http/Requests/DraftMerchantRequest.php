<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class DraftMerchantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
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
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.name' => 'required|string',
            'data.attributes.country_id' => 'required|integer',
            'data.attributes.email' => [
                'required_if:data.attributes.mobile_number,null',
                'email',
                Rule::unique('merchants', 'email')
                    ->whereNull('deleted_at'),
            ],
            'data.attributes.mobile_number' => [
                'required_if:data.attributes.email,null',
            ],
        ];
    }
}
