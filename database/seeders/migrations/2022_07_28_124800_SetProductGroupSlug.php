<?php

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\MerchantProductGroup;

class SetProductGroupSlug_2022_07_28_124800 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            MerchantProductGroup::query()
                ->cursor()
                ->tapEach(function (MerchantProductGroup $group) {
                    $group->forceFill([
                        'slug' => Str::slug($group->name)
                    ])
                    ->save();

                })->all();
        });
    }
}
