<?php

namespace App\Console\Commands\Dns;

use App\Facades\Cloudflare\Zone;
use App\Imports\MerchantSubdomains;
use Illuminate\Console\Command;
use Throwable;

class DnsCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dns:create
        {--path= : The path to the import file}
        {--disk= : The filesystem instance to get the file}
        {--domain=helixpay.ph : The domain}';

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
        if ($this->option('path')) {
            return $this->createFromPath();
        }

        return Command::SUCCESS;
    }

    /**
     * Collect the current DNS records.
     *
     * @param  int  $page
     * @param  \Illuminate\Support\Collection  $dnsRecords
     * @return \Illuminate\Support\Collection
     */
    protected function collectDnsRecords($page = 1, $dnsRecords = null)
    {
        $dnsRecords = $dnsRecords ?? collect();
        $response = Zone::listDnsRecords(compact('page'));

        $dnsRecords = $dnsRecords->merge(
            data_get($response, 'result')
        );

        return $page == data_get($response, 'result_info.total_pages')
            ? $dnsRecords
            : $this->collectDnsRecords($page + 1, $dnsRecords);
    }

    /**
     * Create DNS records from the given subdomain file.
     *
     * @return void
     */
    protected function createFromPath()
    {
        $workbook = (new MerchantSubdomains)
            ->toCollection($this->option('path'), $this->option('disk'));

        $subdomains = $workbook->first()->pluck('subdomain')->unique()->filter()->values();

        if (!$this->confirm("This will create/update {$subdomains->count()} DNS records. Continue?")) {
            return;
        }

        $domain = $this->option('domain');
        $bar = $this->getOutput()->createProgressBar($subdomains->count());
        $dnsRecords = $this->collectDnsRecords();

        $subdomains->each(function ($subdomain) use ($domain, $bar, $dnsRecords) {
            try {
                $subdomain = mb_strtolower($subdomain);

                if ($dnsRecord = $dnsRecords->where('name', "{$subdomain}.{$domain}")->first()) {
                    Zone::patchDnsRecord($dnsRecord['id'] , [
                        'content' => config('bukopay.ip.booking_site'),
                    ]);
                } else {
                    Zone::createDnsRecord($subdomain, config('bukopay.ip.booking_site'));
                }
            } catch (Throwable $e) {}

            $bar->advance();
        });

        $bar->finish();
    }
}
