<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class OAuthClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oauth:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the cached OAuth clients';

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
        Cache::tags('oauth_clients')->flush();

        $this->info('OAuth client cache cleared successfully.');
    }
}
