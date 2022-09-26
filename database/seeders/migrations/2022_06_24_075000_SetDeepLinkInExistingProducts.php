<?php

use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductDeepLink;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Magarrent\LaravelUrlShortener\Models\UrlShortener;

class SetDeepLinkInExistingProducts_2022_06_24_075000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ini_set("memory_limit", "-1");

        DB::transaction(function () {
            Product::query()
                ->whereNull('deleted_at')
                ->with('merchant')
                ->cursor()
                ->tapEach(function (Product $product) {
                    $merchant = $product->merchant;

                    if (!$merchant?->subdomain) return;

                    $scheme = app()->isLocal() ? 'http' : 'https';
                    $slug = $product->slug ?? $this->setSlug($product->title);
                    $summaryUrl = config('bukopay.url.deep_link_summary');

                    $url = "{$scheme}://{$merchant->subdomain}.{$summaryUrl}?product={$slug}";

                    $deepLinkUrl = $product->deepLinks()->make()->generateShortUrl($url);
                    $deepLinkUrl->save();

                    $product->deep_link = $deepLinkUrl->deep_link;
                    $product->saveQuietly();
            })->all();
        });
    }


    /**
     * Set the slug
     *
     * @param  $title
     *
     * @return string
     */
    protected function setSlug($title)
    {
        $slug = Str::slug($title, '-');

        $checkDuplicate = Product::where('slug', $slug)->first();

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

        $checkDuplicate = Product::firstWhere('slug', "{$mainSlug}-{$count}");

        if ($checkDuplicate) {
            $count++;
            return $this->reNameSlug($mainSlug, $count);
        }

        return $mainSlug."-{$count}";
    }
}
