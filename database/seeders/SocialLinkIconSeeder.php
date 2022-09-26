<?php

namespace Database\Seeders;

use App\Models\SocialLinkIcon;
use Illuminate\Database\Seeder;

class SocialLinkIconSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $icons = [
            [
                'name' => 'Viber',
                'path' => 'images/social_links/viber.png',
            ],
            [
                'name' => 'Messenger',
                'path' => 'images/social_links/messenger.png',
            ],
            [
                'name' => 'Telegram',
                'path' => 'images/social_links/telegram.png',
            ],
            [
                'name' => 'Reddit',
                'path' => 'images/social_links/reddit.png',
            ],
            [
                'name' => 'WhatsApp',
                'path' => 'images/social_links/whatsapp.png',
            ],
            [
                'name' => 'Discord',
                'path' => 'images/social_links/discord_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Facebook',
                'path' => 'images/social_links/facebook_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Instagram',
                'path' => 'images/social_links/instagram_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Messenger',
                'path' => 'images/social_links/messenger_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Reddit',
                'path' => 'images/social_links/reddit_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Telegram',
                'path' => 'images/social_links/telegram_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'TikTok',
                'path' => 'images/social_links/tiktok_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Twitch',
                'path' => 'images/social_links/twitch_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Twitter',
                'path' => 'images/social_links/twitter_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Viber',
                'path' => 'images/social_links/viber_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Vimeo',
                'path' => 'images/social_links/vimeo_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Website',
                'path' => 'images/social_links/website_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'WhatsApp',
                'path' => 'images/social_links/whatsapp_footer.png',
                'is_footer' => true,
            ],
            [
                'name' => 'Youtube',
                'path' => 'images/social_links/youtube_footer.png',
                'is_footer' => true,
            ],
        ];

        collect($icons)->each(function ($icon) {
            SocialLinkIcon::firstOrNew([
                'name' => $icon['name'],
                'is_footer' => $icon['is_footer'] ?? false,
            ])->forceFill([
                'path' => $icon['path'],
                'is_footer' => $icon['is_footer'] ?? false,
            ])->save();
        });
    }
}
