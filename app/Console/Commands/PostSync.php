<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\PostService;
use Illuminate\Console\Command;

class PostSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'post:sync
        {--vimeo-transcode : Sync video info of transcoding Vimeo videos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync data for posts with the given criteria';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($this->option('vimeo-transcode')) {
            return $this->syncVimeoVideoInfo();
        }

        return 0;
    }

    /**
     * Sync the video info of posts with transcoding Vimeo videos.
     *
     * @return void
     */
    protected function syncVimeoVideoInfo()
    {
        $posts = Post::query()
            ->where('type', Post::TYPE_VIDEO)
            ->where('video_type', Post::VIDEO_VIMEO)
            ->whereNotNull('video_id')
            ->where('video_info->transcode->status', '<>', 'complete')
            ->get();

        if ($posts->isEmpty()) {
            return;
        }

        $this->withProgressBar($posts, function (Post $post) {
            (new PostService)->fetchVideoInfo($post)->save();
        });

        $this->getOutput()->success("{$posts->count()} Vimeo video posts successfully updated");
    }
}
