<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;

class SetDefaultCopies_2022_07_15_105500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Merchant::query()->update(
            (new Merchant)->mergeDefaults()->toArray()
        );
    }
}
