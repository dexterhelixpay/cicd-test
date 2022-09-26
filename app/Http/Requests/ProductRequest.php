<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $user = $this->userOrClient();

        if ($user instanceof User) {
            return true;
        }

        if ($user instanceof MerchantUser) {
            $product = $this->route('product');

            if (!$product instanceof Product) {
                $product = Product::find($product);
            }

            return optional($product)->merchant_id === $user->merchant_id;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $product = $this->route('product');

        if (!$product instanceof Product) {
            $product = Product::find($product);
        }

        if ($merchantUser = $this->isFromMerchant()) {
            $merchant = $merchantUser->merchant()->first();
        } else {
            $merchant = Merchant::find($this->input('merchant_id'));
        }

        return [
            'merchant_id' => [
                Rule::requiredIf(!$merchantUser),
                Rule::exists('merchants', 'id')
                    ->where('is_enabled', true)
                    ->withoutTrashed(),
            ],

            'title' => [
                Rule::when($product, 'sometimes', 'required'),
                'string',
                'max:255',
            ],

            'slug' => [
                'sometimes',
                'slug',
                Rule::unique('products', 'slug')
                    ->when($merchant, function ($rule, $merchant) {
                        $rule->where('merchant_id', $merchant->getKey());
                    })
                    ->when($product, function ($rule, $product) {
                        $rule->ignore($product);
                    }),
            ],

            'meta_title' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
            'meta_description' => [
                'sometimes',
                'nullable',
                'string',
                'max:160',
            ],

            'video_banner' => [
                'bail',
                'sometimes',
                'nullable',
                'active_url',
                'video_url',
            ],

            'is_visible' => [
                'sometimes',
                'boolean',
            ],
            'are_multiple_orders_allowed' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
