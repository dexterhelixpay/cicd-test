<?php

namespace App\Http\Requests\ProductVariant;

use App\Http\Requests\Contracts\WithMerchant;
use App\Rules\ProductVariantExists;
use Illuminate\Validation\Rule;

class BulkUpdateProductVariantRequest extends WithMerchant
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            '*' => 'required|array',
            '*.id' => [
                'required',
                new ProductVariantExists($this->merchant, true),
            ],
            '*.stock' => 'sometimes|nullable|integer|min:0|max:999999',
            '*.added_stock' => 'sometimes|integer|min:0|max:999999',
        ];
    }
}
