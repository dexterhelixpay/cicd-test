<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class EmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->isFromUser() || $this->isFromMerchant();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.banner_image_path' => [
                Rule::when(
                    $this->hasFile('data.attributes.banner_image_path'),
                    'image',
                )
            ]
        ];
    }
}
