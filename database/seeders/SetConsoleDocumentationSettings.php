<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SetConsoleDocumentationSettings extends Seeder
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
                'key' => 'IsConsoleDocumentationEnabled',
                'value' => 0,
                'value_type' => 'boolean',
            ])
            ->save();
    }
}
