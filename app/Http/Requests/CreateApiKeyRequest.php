<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateApiKeyRequest extends FormRequest
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
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$this->userOrClient()) {
            return false;
        }

        return $this->merchant->apiKeys()->count() < setting('MaxMerchantApiKeys', 3);
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->user = $this->userOrClient();
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
