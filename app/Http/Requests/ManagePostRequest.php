<?php

namespace App\Http\Requests;

use App\Http\Requests\Contracts\WithMerchant;
use App\Models\MerchantUser;
use App\Rules\VimeoVideo;
use Illuminate\Validation\Rule;

class ManagePostRequest extends WithMerchant
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
            ? $user->merchant_id == $this->route('post')->merchant_id
            : true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'headline' => 'sometimes|required|string|max:255',
            'subheadline' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'body' => 'sometimes|nullable|string',

            'banner' => 'sometimes|image',
            'banner_link' => 'sometimes|nullable|url',

            'video_id' => 'sometimes',

            'products.*' => [
                'sometimes',
                Rule::exists('products', 'id')
                    ->when($this->merchant, function ($query) {
                        $query->where('merchant_id', $this->merchant->getKey());
                    })
                    ->withoutTrashed(),
            ],

            'is_published' => 'sometimes|boolean',
            'is_visible' => 'sometimes|boolean',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->sometimes(
            'video_id',
            new VimeoVideo,
            function ($input) {
                return is_null($input->video_type)
                    || $input->video_type === 'VIMEO'
                    || $this->route('post')->video_type === 'VIMEO';
            }
        );
    }
}
