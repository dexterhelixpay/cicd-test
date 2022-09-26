<?php

namespace App\Console\Commands;

use App\Facades\Cloudflare\Zone;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DnsCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dns:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up test subdomains';

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
        if (!app()->environment('sandbox', 'staging')) {
            return $this->getOutput()
                ->error('The command can only be run on sandbox/staging environments.');
        }

        $dnsRecords = $this->collectDnsRecords();

        if ($dnsRecords->isEmpty()) {
            return $this->getOutput()->success('No test subdomains found');
        }

        $this->line('');
        $this->table(['#', 'ID', 'Name'], $dnsRecords->map(function ($record, $index) {
            return [$index + 1, $record['id'], $record['name']];
        }));

        if (!$this->confirm('Are you sure you want to delete the given DNS records?')) {
            return;
        }

        $bar = $this->getOutput()->createProgressBar($dnsRecords->count());

        $dnsRecords->each(function ($record) use ($bar) {
            Zone::deleteDnsRecord($record['id']);

            $bar->advance();
        });

        $bar->finish();

        return 0;
    }

    /**
     * @param  int  $page
     * @param  \Illuminate\Support\Collection
     * @return \Illuminate\Support\Collection
     */
    protected function collectDnsRecords($page = 1, $dnsRecords = null)
    {
        $dnsRecords = $dnsRecords ?? collect();
        $response = Zone::listDnsRecords(compact('page'));
        $reservedSubdomains = collect(['api', 'console', 'cdn', 'cp', 'payment', 'preview'])
            ->map(function ($subdomain) {
                return [
                    "staging-{$subdomain}.bukopay.ph",
                    "sandbox-{$subdomain}.bukopay.ph",
                ];
            })
            ->flatten();

        $dnsRecords = $dnsRecords->merge(
            collect(data_get($response, 'result'))
                ->filter(function ($record) use ($reservedSubdomains) {
                    return Str::startsWith($record['name'], ['staging-', 'sandbox-'])
                        && !$reservedSubdomains->contains($record['name']);
                })
        );

        return $page == data_get($response, 'result_info.total_pages')
            ? $dnsRecords
            : $this->collectDnsRecords($page + 1, $dnsRecords);
    }
}
