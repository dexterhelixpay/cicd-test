<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateCPUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create-cp:user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a control panel user.';

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
        $name = $this->ask('What is the user name?');
        $email = $this->ask('What is the user email address?');

        if (!$name || !$email) {
            return $this->error('Name and Email is required.');
        }

        if (User::firstWhere('email', $email)) {
            return $this->error('Email address is already taken.');
        }

        DB::transaction(function () use ($name, $email) {
            User::create([
                'name' => $name,
                'email' => $email,
            ]);
        });
    }
}
