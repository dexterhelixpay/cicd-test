<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class KycLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Setting::make()
            ->forceFill([
                'key' => 'KycLink',
                'value' => 'https://bit.ly/HelixPayKYCDocs',
                'value_type' => 'string'
            ])
            ->save();
    }
}
