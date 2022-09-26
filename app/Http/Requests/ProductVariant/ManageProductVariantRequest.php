<?php

namespace App\Http\Requests\ProductVariant;

use App\Models\MerchantUser;
use Illuminate\Foundation\Http\FormRequest;

class ManageProductVariantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$user = $this->user()) {
            return false;
        }

        return $user instanceof MerchantUser
            ? $user->merchant_id == $this->route('product_variant')?->product?->merchant_id
            : true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        if ($this->isMethod('GET')) {
            return [];
        }

        return [
            'sold' => 'sometimes|integer|min:0',
            'stock' => 'sometimes|nullable|integer|min:0',
        ];
    }
}
