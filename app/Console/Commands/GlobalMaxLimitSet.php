<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GlobalMaxLimitSet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'global-max-limit:set
        {--limit=250000 : The maximum limit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set global max limit';

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
        DB::transaction(function () {
            $setting = Setting::where('key', 'MerchantMaxAmountLimit')->first();

            if ($setting) {
                $setting->forceFill([
                    'value' => $this->option('limit') ?? 250000
                ])->update();
            } else {
                $setting = Setting::make()->forceFill([
                    'key' => 'MerchantMaxAmountLimit',
                    'value' => $this->option('limit') ?? 250000,
                    'value_type' => 'float',
                ])->save();
            }
        });
    }
}
