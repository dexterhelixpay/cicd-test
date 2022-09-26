<?php

namespace App\Console\Commands\Webhook;

use App\Facades\PayMaya;
use App\Facades\PayMongo;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class WebhookList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:list {gateway : The payment gateway}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all webhooks for the gateway';

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
        switch ($this->argument('gateway')) {
            case 'paymaya':
                return $this->listPayMayaWebhooks();

            case 'paymongo':
                return $this->listPayMongoWebhooks();

            default:
                //
        }
    }

    /**
     * List all webhooks for PayMaya.
     *
     * @return void
     */
    protected function listPayMayaWebhooks()
    {
        try {
            $response = PayMaya::payments(true)->get('webhooks');

            foreach (json_decode($response->getBody(), true) as $webhook) {
                $this->line('');
                $this->table(['Key', 'Value'], assoc_table($webhook));
            }
        } catch (ClientException $e) {
            $this->getOutput()->error($e->getMessage());
        }
    }

    /**
     * List all webhooks for PayMongo.
     *
     * @return void
     */
    protected function listPayMongoWebhooks()
    {
        try {
            $response = PayMongo::client()->get('webhooks');
            $data = data_get(json_decode($response->getBody(), true), 'data', []);

            collect($data)->each(function ($webhook) {
                $data = collect(array_merge(Arr::only($webhook, 'id'), $webhook['attributes']))
                    ->map(function ($value) {
                        if (is_bool($value)) {
                            return $value ? 'true' : 'false';
                        }

                        if (is_array($value)) {
                            return join(', ', $value);
                        }

                        return $value;
                    })
                    ->toArray();

                $this->line('');
                $this->table(['Key', 'Value'], assoc_table($data));
            });
        } catch (ClientException $e) {
            $this->getOutput()->error($e->getMessage());
        }
    }
}
