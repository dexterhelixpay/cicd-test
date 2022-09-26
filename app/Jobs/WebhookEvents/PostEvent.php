<?php

namespace App\Jobs\WebhookEvents;

use App\Models\Merchant;
use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;

abstract class PostEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    protected $merchant;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Webhook::query()
            ->where('merchant_id', $this->merchant->getKey())
            ->whereJsonContains('events', $this->getEvent())
            ->cursor()
            ->tapEach(function (Webhook $webhook) {
                $timestamp = now()->toDateTimeString();

                $http = Http::asJson()
                    ->acceptJson();

                if ($signature = $this->getSignature($timestamp)) {
                    $http->withHeaders([
                        'X-HelixPay-Signature' => $signature,
                        'X-Helixpay-Signature-Timestamp' => $timestamp,
                    ]);
                }

                $request = $this->merchant->webhookRequests()
                    ->make([
                        'request_method' => 'POST',
                        'request_url' => $webhook->url,
                        'request_headers' => data_get($http->getOptions(), 'headers') ?: null,
                        'request_body' => [
                            'event' => $this->getEvent(),
                            'data' => $this->getData(),
                        ],
                    ])
                    ->webhook()
                    ->associate($webhook);

                try {
                    $response = $http->post(
                        $request->request_url,
                        $request->request_body->toArray()
                    );

                    $request->fill([
                        'response_status' => $response->status(),
                        'response_headers' => $response->headers() ?: null,
                        'response_body' => $response->json() ?: null,
                        'is_successful' => $response->successful(),
                    ]);
                } catch (ConnectionException $e) {
                    $request->error_info = [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ];
                } finally {
                    $request->save();
                }
            })
            ->all();
    }

    /**
     * Create a signature
     *
     * @param  mixed $timestamp
     *
     * @return string
     */
    public function getSignature($timestamp)
    {
        $webhook = $this->merchant->webhookKey()->first();

        if (!$webhook) return null;

        return base64_encode(
            hash_hmac('sha256', $timestamp, $webhook->key)
        );
    }

    /**
     * Post the webhook event to the given merchant.
     *
     * @param  int|string|\App\Models\Merchant  $merchant
     * @return self
     */
    public function postTo($merchant)
    {
        $this->merchant = $merchant instanceof Merchant
            ? $merchant
            : Merchant::find($merchant);

        return $this;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    abstract public function getEvent(): string;

    /**
     * Get the event data.
     *
     * @return array
     */
    abstract public function getData(): array;
}
