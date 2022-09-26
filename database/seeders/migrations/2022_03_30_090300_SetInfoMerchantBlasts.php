<?php

use App\Models\MerchantEmailBlast;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetInfoMerchantBlasts_2022_03_30_090300 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            MerchantEmailBlast::query()
                ->cursor()
                ->tapEach(function (MerchantEmailBlast $blast)  {
                    $blast->update([
                        'slug' => $this->setSlug($blast),
                        'published_at' => $blast->created_at
                    ]);
                })
                ->all();
        });
    }

    /**
     * Set the slug
     *
     * @param  \App\Models\MerchantEmailBlast  $emailBlast
     * @return void
     */
    protected function setSlug(MerchantEmailBlast $emailBlast)
    {
        $slug = Str::slug($emailBlast->title, '-');

        $checkDuplicate = MerchantEmailBlast::where('slug', $slug)
            ->where('id', '!=', $emailBlast->id)
            ->first();

        if ($checkDuplicate) {
            $slug = $this->reNameSlug($slug);
        }

        return $slug;
    }

    /**
     * Rename the duplicated slug.
     *
     * @param  string  $slug
     * @param  int  $count
     *
     * @return string
     */
    protected function reNameSlug($slug, $count = 0)
    {
        $mainSlug = $slug;

        if ($count === 0 ) {
            $count += 1;
        }

        $checkDuplicate = MerchantEmailBlast::firstWhere('slug', "{$mainSlug}-{$count}");

        if ($checkDuplicate) {
            $count++;
            return $this->reNameSlug($mainSlug, $count);
        }

        return $mainSlug."-{$count}";
    }
}
