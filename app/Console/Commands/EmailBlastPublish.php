<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailBlast;
use App\Models\MerchantEmailBlast;
use Illuminate\Console\Command;

class EmailBlastPublish extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email-blast:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish and send an email blast to targeted customers';

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
        MerchantEmailBlast::query()
            ->where('is_draft', false)
            ->whereNull('deleted_at')
            ->where('is_published', false)
            ->where('published_at', '<=', now()->toDateTimeString())
            ->cursor()
            ->tapEach(function (MerchantEmailBlast $emailBlast) {
                dispatch(new SendEmailBlast($emailBlast));

                $emailBlast->forceFill([
                    'is_published' => true
                ])->update();

            })->all();
    }
}
