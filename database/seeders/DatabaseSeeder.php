<?php

namespace Database\Seeders;

use App\Models\DbSeeder;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        # For seeders on migrations folder add "\\" on the start of the seeder name

        $seeders =[
            "\\SetFAQsDefaultToDisable_2022_09_05_142300",
            '\\SetDeliverySettingsDefaultToFalse_2022_09_07_110000',
            '\\SetMembersPageButtonToMerchant_2022_09_09_004700',
            '\\ConvertS3ImageToWebp_2022_09_06_214300',
        ];

        collect($seeders)
            ->each(function($seeder) {
                if (Str::contains($seeder, '\\')) {
                    $this->execute($seeder, true);
                    return;
                }

                $this->execute($seeder);
            });
    }

    /**
     * Validate and run the seeder.
     *
     * @param  string  $seeder
     * @param  boolean  $migration
     * @return void
     */
    public function execute($seeder, $migration = false)
    {
        if (DbSeeder::byName($seeder)) return;

        $migration
            ? $this->call($seeder)
            : $this->call("Database\\Seeders\\".$seeder);

        DbSeeder::firstOrCreate(['name' => $seeder]);

    }
}
