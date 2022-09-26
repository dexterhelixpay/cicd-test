<?php

namespace App\Observers;

use App\Jobs\SendEmailBlast;
use App\Models\MerchantEmailBlast;
use Illuminate\Support\Str;
class EmailBlastObserver
{
    /**
     * Handle the merchant "creating" event.
     *
     * @param  \App\Models\MerchantEmailBlast  $emailBlast
     * @return void
     */
    public function creating($emailBlast)
    {
        $this->setSlug($emailBlast);
    }

    /**
     * Handle the merchant "created" event.
     *
     * @param  \App\Models\MerchantEmailBlast  $emailBlast
     * @return void
     */
    public function created($emailBlast)
    {
        $this->notifyTargetedCustomers($emailBlast);
    }

    /**
     * Handle the merchant "updating" event.
     *
     * @param  \App\Models\MerchantEmailBlast  $emailBlast
     * @return void
     */
    public function updating($emailBlast)
    {
        $this->setSlug($emailBlast);
    }

    /**
     * Handle the merchant "updating" event.
     *
     * @param  \App\Models\MerchantEmailBlast  $emailBlast
     * @return void
     */
    public function updated($emailBlast)
    {
        if (!$emailBlast->is_draft && $emailBlast->wasChanged('is_draft')) {
            $this->notifyTargetedCustomers($emailBlast);
        }
    }

    /**
     * Notify the targeted customer about he email blast.
     *
     * @param  \App\Models\MerchantEmailBlast  $emailBlast
     * @return void
     */
    public function notifyTargetedCustomers($emailBlast)
    {
        if (!$emailBlast->is_published || $emailBlast->is_draft) return;

        dispatch(new SendEmailBlast($emailBlast));
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

        $emailBlast->slug = $slug;
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
