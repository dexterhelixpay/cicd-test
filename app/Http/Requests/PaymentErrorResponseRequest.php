<?php

namespace App\Http\Requests;

use App\Models\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentErrorResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (bool) $this->isFromUser();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ($this->isMethod('DELETE')) {
            return [];
        }

        return [
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.error_codes' => 'nullable|array',
            'data.attributes.error_codes.*' => 'string',
            'data.attributes.payment_type_id' => [
                Rule::exists('payment_types', 'id')
                    ->whereNot('id', PaymentType::CASH),
            ],
            'data.attributes.title' => 'string|max:255',
            'data.attributes.subtitle' => 'string|max:65535',
            'data.attributes.is_enabled' => 'boolean',
        ];
    }
}
