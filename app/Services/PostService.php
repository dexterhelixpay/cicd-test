<?php

namespace App\Services;

use App\Facades\Vimeo;
use App\Models\MerchantEmailBlast;
use App\Models\Post;
use App\Models\Product;

class PostService
{
    /**
     * Create an email blast equivalent of the given post.
     *
     * @param  \App\Models\Post  $post
     * @return \App\Models\MerchantEmailBlast|null
     */
    public function createBlast(Post $post)
    {
        if ($post->type !== Post::TYPE_BLOG) {
            return;
        }
    }

    /**
     * Create a post from the given email blast.
     *
     * @param  \App\Models\MerchantEmailBlast  $blast
     * @return \App\Models\Post|null
     */
    public function createFromBlast(MerchantEmailBlast $blast)
    {
        $blast->load('products');

        if ($blast->products->isEmpty()) {
            return;
        }

        /** @var \App\Models\Post */
        $post = $blast->post()->firstOrNew();

        $post->fill([
            'type' => Post::TYPE_BLOG,
            'headline' => $blast->title,
            'subheadline' => $blast->subtitle,
            'body' => $blast->body,

            'is_visible' => !$blast->is_draft,
        ]);

        if (!$blast->is_draft) {
            $post->published_at = $post->published_at ?? now();
        }

        if ($blast->banner_image_path) {
            $post->banner_image_path = $blast->getRawOriginal('banner_image_path');
            $post->banner_link = $blast->banner_url;
        }

        $post->merchant()->associate($blast->merchant_id)->save();

        $post->products()->sync(
            $blast->products->mapWithKeys(function (Product $product) {
                return [$product->getKey() => ['expires_at' => $product->pivot->expires_at]];
            })
        );

        return $post;
    }

    /**
     * Fetch the video information of the given post.
     *
     * @param  \App\Models\Post  $post
     * @return \App\Models\Post
     */
    public function fetchVideoInfo(Post $post)
    {
        if ($post->type !== Post::TYPE_VIDEO) {
            return $post->forceFill([
                'video_id' => null,
                'video_url' => null,
                'video_embed_url' => null,
                'video_embed_html' => null,
                'video_info' => null,
            ]);
        }

        $post->video_type = $post->video_type ?? Post::VIDEO_VIMEO;

        switch ($post->video_type) {
            case Post::VIDEO_VIMEO:
                $response = Vimeo::videos()->find($post->video_id);

                if ($response->successful()) {
                    $post->forceFill([
                        'video_url' => $response->json('link'),
                        'video_embed_url' => $response->json('player_embed_url'),
                        'video_embed_html' => $response->json('embed.html'),
                        'video_info' => $response->json(),
                    ]);
                }

                break;

            default:
                //
        }

        return $post;
    }
}
