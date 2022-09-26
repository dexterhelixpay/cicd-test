<?php

namespace App\Http\Requests;

use App\Http\Requests\Contracts\WithMerchant;
use App\Rules\PaymentFrequency;
use App\Rules\ProductVariantExists;
use Illuminate\Validation\Rule;

class CheckStocksRequest extends WithMerchant
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
        return array_merge(parent::rules(), [
            '*.product_id' => [
                'required_without:*.product_variant_id',
                Rule::exists('products', 'id')
                    ->where('merchant_id', $this->merchant->getKey())
                    ->withoutTrashed(),
            ],
            '*.product_variant_id' => [
                'required_without:*.product_id',
                new ProductVariantExists($this->merchant),
            ],
            '*.payment_schedule.frequency' => [
                'required_without:*.product_variant_id',
                new PaymentFrequency,
            ],
            '*.quantity' => 'required|integer|min:1',
        ]);
    }
}
