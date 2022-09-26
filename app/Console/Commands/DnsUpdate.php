<?php

namespace App\Console\Commands;

use App\Facades\Cloudflare\Zone;
use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

class DnsUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dns:update
        {content : The new DNS record content}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the content of existing DNS records';

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
        Merchant::query()
            ->whereNotNull('subdomain')
            ->whereNotNull('verified_at')
            ->cursor()
            ->tapEach(function (Merchant $merchant) {
                try {
                    $results = Zone::listDnsRecords(['name' => "{$merchant->subdomain}.bukopay.ph"]);
                    if (!count($results['result'] ?? [])) return;

                    $record = Arr::first($results['result']);

                    Zone::patchDnsRecord($record['id'], [
                        'content' => $this->argument('content'),
                    ]);
                } catch (Throwable $e) {}
            })
            ->all();

        return 0;
    }
}
