<?php

namespace App\Console\Commands;

use App\Models\MerchantUser;
use App\Models\User;
use App\Notifications\PasswordExpiring;
use Illuminate\Console\Command;

class PasswordRemind extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'password:remind';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind users about their expiring passwords';

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
        User::expiringPassword()->get()->each(function (User $user) {
            $user->notify(new PasswordExpiring);
        });

        MerchantUser::expiringPassword()->get()->each(function (MerchantUser $user) {
            $user->notify(new PasswordExpiring);
        });

        return 0;
    }
}
