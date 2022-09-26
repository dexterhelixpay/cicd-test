<?php

use App\Libraries\Image;
use App\Models\Merchant;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConvertS3ImageToWebp_2022_09_06_214300 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Merchant::query()
            ->chunk(20, function (Collection $merchants, $page) {
                $this->command->getOutput()->info('Seeding page ' . $page . '.');

                DB::transaction(function () use ($merchants) {
                    $this->command->withProgressBar(
                        $merchants,
                        function (Merchant $merchant) {
                            $this->convertImage($merchant);
                        }
                    );
                });
            });
    }

    public function convertImage(Merchant $merchant)
    {
        $imageColumns = collect([
            'logo_image_path',
            'home_banner_path',
            'storefront_background_image_path',
            'customer_promo_image_path',
            'membership_banner_path',
            'members_login_banner_path'
        ]);

        $s3 = Storage::disk('s3');

        $imageColumns
            ->each(function($column) use ($merchant, $s3) {
                $origImagePath = $merchant->getRawOriginal($column);

                if (
                    !$origImagePath ||
                    Str::contains($origImagePath, ['.webp','.svg','.bmp','.gif'])
                ) {
                    return;
                }

                $file = $s3->get($origImagePath);

                if (!Storage::disk('s3')->exists($origImagePath)) return;

                $newImagePath = substr_replace($origImagePath, '.webp', strrpos($origImagePath, '.'));

                $merchant->setAttribute($column, $newImagePath)->update();

                $image = new Image($file);
                $image->encode('webp');
                $image->put($newImagePath);
            });
    }
}
