<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarketingCardCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'marketing-card:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all marketing cards based on the expiration date.';

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
            Merchant::query()
                ->whereNotNull('marketing_card_image_path')
                ->whereNotNull('marketing_card_expires_at')
                ->whereDate('marketing_card_expires_at', now()->toDateString())
                ->update([
                    'marketing_card_image_path' => null,
                    'marketing_card_image_url' => null,
                    'marketing_card_expires_at' => null,
                ]);
        });
        
        return 0;
    }
}
