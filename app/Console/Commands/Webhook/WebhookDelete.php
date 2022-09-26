<?php

namespace App\Console\Commands\Webhook;

use App\Facades\PayMaya;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;

class WebhookDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:delete {gateway : The payment gateway}
        {id : The webhook ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the given webhook from the gateway';

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
                return $this->deletePayMayaWebhook();

            default:
                //
        }
    }

    /**
     * Delete the given webhook from PayMaya.
     *
     * @return void
     */
    protected function deletePayMayaWebhook()
    {
        try {
            $response = PayMaya::payments(true)
                ->delete("webhooks/{$this->argument('id')}");

            $this->table(['Key', 'Value'], assoc_table(json_decode($response->getBody(), true)));

            $this->getOutput()->success('Webhook deleted successfully.');
        } catch (ClientException $e) {
            $this->getOutput()->error($e->getMessage());
        }
    }
}
