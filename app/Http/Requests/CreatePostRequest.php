<?php

namespace App\Http\Requests;

use App\Http\Requests\Contracts\WithMerchant;
use App\Models\Post;
use App\Rules\VimeoVideo;
use Illuminate\Validation\Rule;

class CreatePostRequest extends WithMerchant
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
            'type' => [
                'required',
                Rule::in([Post::TYPE_BLOG, Post::TYPE_VIDEO]),
            ],

            'headline' => 'required|string|max:255',
            'subheadline' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'body' => 'sometimes|nullable|string',

            'banner' => [
                'required_if:type,' . Post::TYPE_VIDEO,
                'image',
            ],
            'banner_link' => 'sometimes|nullable|url',

            'video_id' => 'required_if:type,' . Post::TYPE_VIDEO,

            'products' => 'required',
            'products.*' => [
                'sometimes',
                Rule::exists('products', 'id')
                    ->where('is_membership', true)
                    ->when($this->merchant, function ($query) {
                        $query->where('merchant_id', $this->merchant->getKey());
                    })
                    ->withoutTrashed(),
            ],

            'is_published' => 'sometimes|boolean',
        ]);
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
                return is_null($input->video_type) || $input->video_type === 'VIMEO';
            }
        );
    }
}
