<?php

namespace App\Http\Requests\Contracts;

use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class WithMerchant extends FormRequest
{
    /**
     * The user.
     *
     * @var \Illuminate\Foundation\Auth\User
     */
    public $user;

    /**
     * The merchant.
     *
     * @var \App\Models\Merchant|null
     */
    public $merchant;

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->user = $this->user ?? $this->userOrClient();
        $this->merchant = $this->user instanceof MerchantUser
            ? $this->user->merchant
            : Merchant::find($this->input('merchant_id'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'merchant_id' => [
                Rule::requiredIf(!$this->user instanceof MerchantUser),
                Rule::exists('merchants', 'id')
                    ->where('is_enabled', true)
                    ->whereNotNull('verified_at')
                    ->withoutTrashed(),
            ],
        ];
    }
}
