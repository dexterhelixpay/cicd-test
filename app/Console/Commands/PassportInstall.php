<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PassportInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bk-passport:install
        {--show : Display the clients instead of modifying files}
        {--length=4096 : The length of the private key}
        {--force : Overwrite keys they already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the commands necessary to prepare Passport for use';

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
     * @return mixed
     */
    public function handle()
    {
        $this->call('passport:keys', [
            '--force' => $this->option('force'),
            '--length' => $this->option('length'),
        ]);

        DB::table('oauth_clients')->truncate();

        foreach (array_keys(config('auth.providers')) as $provider) {
            if ($provider === 'null') {
                continue;
            }

            $name = ucwords(str_replace('_', ' ', $provider));

            $this->call('passport:client', [
                '--password' => true,
                '--name' => $name . ' Password Grant Client',
                '--provider' => $provider,
            ]);

            $this->line('<comment>Client provider:</comment> ' . $name);

            $this->call('passport:client', [
                '--personal' => true,
                '--name' => $name . ' Personal Access Client',
                '--provider' => $provider,
            ]);

            $this->line('<comment>Client provider:</comment> ' . $name);
        }

        $this->call('oauth:clear');
    }
}
