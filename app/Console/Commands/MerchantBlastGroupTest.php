<?php

namespace App\Console\Commands;

use App\Models\MerchantEmailBlast;
use App\Models\MerchantProductGroup;
use Illuminate\Console\Command;

class MerchantBlastGroupTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'merchant-blast-group:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $merchantEmailBlast = MerchantEmailBlast::find(21);
        $productGroup = MerchantProductGroup::find(179);


        $merchantEmailBlast->productGroups()->sync([
            179 => [
                'expires_at' => now()
            ],
            180 => [
                'expires_at' => null
            ],
            183 => [
                'expires_at' => null
            ]
        ]);

        \Log::info(
            $productGroup->emailBlasts()->get()->toArray()
        );
    }
}
