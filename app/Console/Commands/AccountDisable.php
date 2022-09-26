<?php

namespace App\Console\Commands;

use App\Models\MerchantUser;
use App\Models\User;
use Illuminate\Console\Command;

class AccountDisable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'account:disable
        {--cpDays= : The number of days since last activity of cp users}
        {--consoleDays= : The number of days since last activity of console users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable inactive accounts based on the last activity';

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
        $cpDays = $this->option('cpDays') ?? setting('CPInActiveDays', 90);
        $consoleDays = $this->option('consoleDays') ?? setting('ConsoleInActiveDays', 90);

        User::query()
            ->where('is_enabled', true)
            ->whereHas('lastRequest', function($query) use($cpDays) {
                $query->whereDate(
                    'updated_at',
                    '<=',
                    now()->subDays($cpDays)->toDateString()
                );
            })
            ->get()
            ->each(function (User $user) {
                $user->update(['is_enabled' => false]);
            });

        MerchantUser::query()
            ->where('is_enabled', true)
            ->whereHas('lastRequest', function($query) use($consoleDays) {
                $query->whereDate(
                    'updated_at',
                    '<=',
                    now()->subDays($consoleDays)->toDateString()
                );
            })
            ->get()
            ->each(function (MerchantUser $user) {
                $user->update(['is_enabled' => false]);
            });
    }
}
