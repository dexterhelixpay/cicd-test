<?php

namespace Database\Seeders;

use App\Models\MerchantEmailBlast;
use App\Services\PostService;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        MerchantEmailBlast::query()
            ->cursor()
            ->tapEach(function (MerchantEmailBlast $emailBlast) {
                (new PostService)->createFromBlast($emailBlast);
            })
            ->all();
    }
}
