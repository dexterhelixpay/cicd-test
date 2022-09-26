<?php

namespace App\Console\Commands\Webhook;

use App\Facades\PayMaya;
use App\Facades\PayMongo;
use Arr;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class WebhookCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "webhook:create {gateway : The payment gateway}
        {--url= : The webhook's URL}
        {--events=* : The events to be associated with the webhook}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a webhook for the gateway';

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
                return $this->createPayMayaWebhook();

            case 'paymongo':
                return $this->createPayMongoWebhook();

            default:
                //
        }
    }

    /**
     * Ask for the webhook's URL.
     *
     * @return string
     */
    protected function askForUrl()
    {
        $url = $this->option('url');

        while (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = $this->ask("Enter the webhook's URL");
        }

        return $url;
    }

    /**
     * Create a webhook for PayMongo.
     *
     * @return void
     */
    protected function createPayMongoWebhook()
    {
        $validEvents = collect(PayMongo::EVENTS);
        $events = collect($this->option('events'))->intersect($validEvents);

        if (!$events->count()) {
            return $this->getOutput()->error(
                'At least one of these events is required: '. $validEvents->join(', ')
            );
        }

        $url = $this->askForUrl();

        try {
            $response = PayMongo::client()->post('webhooks', [
                'json' => [
                    'data' => [
                        'attributes' => [
                            'url' => $url,
                            'events' => $events->toArray(),
                        ],
                    ],
                ],
            ]);

            $data = json_decode($response->getBody(), true)['data'];

            $this->table(
                ['Key', 'Value'],
                assoc_table(array_merge(Arr::only($data, 'id'), $data['attributes']))
            );

            $this->getOutput()->success('Webhook created successfully.');
        } catch (BadResponseException $e) {
            $this->getOutput()->error($e->getMessage());
        }
    }

    /**
     * Create a webhook for PayMaya.
     *
     * @return void
     */
    protected function createPayMayaWebhook()
    {
        $validEvents = collect(PayMaya::EVENTS);
        $events = collect($this->option('events'))->intersect($validEvents);

        if (!$events->count()) {
            return $this->output->error(
                'At least one of these events is required: '. $validEvents->join(', ')
            );
        }

        $url = $this->askForUrl();

        try {
            $response = Paymaya::payments(true)->get('webhooks');

            $currentWebhooks = collect(json_decode($response->getBody(), true));
        } catch (Throwable $e) {
            $currentWebhooks = collect();
        }

        $webhooks = $events
            ->map(function ($event) use ($url, $currentWebhooks) {
                if ($webhook = $currentWebhooks->where('name', $event)->first()) {
                    Artisan::call('webhook:delete', [
                        'gateway' => 'paymaya',
                        'id' => $webhook['id'],
                    ]);
                }

                try {
                    $response = PayMaya::payments(true)->post('webhooks', [
                        'json' => [
                            'name' => $event,
                            'callbackUrl' => $url,
                        ],
                    ]);

                    return json_decode($response->getBody(), true);
                } catch (Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->each(function ($webhook) {
                $this->line('');
                $this->table(['Key', 'Value'], assoc_table($webhook));
            });

        if ($webhooks->isNotEmpty()) {
            $this->getOutput()->success('Webhook/s created successfully.');
        }
    }
}
