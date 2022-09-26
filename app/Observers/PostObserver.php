<?php

namespace App\Observers;

use App\Facades\Vimeo;
use App\Models\Post;
use Illuminate\Support\Str;

class PostObserver
{
    /**
     * Handle the post "creating" event.
     *
     * @param  \App\Models\Post  $post
     * @return void
     */
    public function creating(Post $post)
    {
        Str::slugFor($post, 'headline');
    }

    /**
     * Handle the post "created" event.
     *
     * @param  \App\Models\Post  $post
     * @return void
     */
    public function created(Post $post)
    {
        $this->updateVimeoVideo($post);
    }

    /**
     * Handle the post "updating" event.
     *
     * @param  \App\Models\Post  $post
     * @return void
     */
    public function updating(Post $post)
    {
        if ($post->isDirty('headline')) {
            Str::slugFor($post, 'headline');
        }
    }

    /**
     * Handle the post "updated" event.
     *
     * @param  \App\Models\Post  $post
     * @return void
     */
    public function updated(Post $post)
    {
        $this->updateVimeoVideo($post);
    }

    /**
     * Update the video on Vimeo.
     *
     * @param  \App\Models\Post  $post
     * @return void
     */
    protected function updateVimeoVideo(Post $post)
    {
        if ($post->type !== Post::TYPE_VIDEO || $post->video_type !== Post::VIDEO_VIMEO) {
            return;
        }

        if ($post->wasRecentlyCreated || $post->wasChanged('headline', 'description')) {
            Vimeo::videos()->update($post->video_id, [
                'name' => $post->headline,
                'description' => $post->description,
                'embed' => [
                    'buttons' => [
                        'embed' => false,
                        'fullscreen' => true,
                        'hd' => true,
                        'like' => false,
                        'scaling' => true,
                        'share' => false,
                        'watchlater' => false,
                    ],
                    'end_screen' => [
                        'type' => 'empty',
                    ],
                    'logos' => [
                        'vimeo' => false,
                    ],
                    'title' => [
                        'name' => 'hide',
                        'owner' => 'hide',
                        'portrait' => 'hide',
                    ],
                ],
                'privacy' => [
                    'add' => false,
                    'comments' => 'nobody',
                    'download' => false,
                    'view' => 'disable',
                ],
            ]);
        }
    }
}
