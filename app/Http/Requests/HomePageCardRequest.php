<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class HomePageCardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->isFromUser();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ($this->is('v1/home_page_cards/sort')) {
            return [
                'data' => 'required',
                'data.attributes' => 'required'
            ];
        }

        return [
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.image_path' => [
                Rule::when(
                    $this->hasFile('data.attributes.image_path'),
                    'image',
                    'string'
                )
            ],
            'data.attributes.card_link' =>[
                Rule::requiredIf(!$this->hasOnly('is_enabled', 'data.attributes')),
                'nullable',
                'array'
            ],
        ];
    }
}
