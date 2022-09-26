<?php

namespace App\Libraries\Cloudflare;

use Illuminate\Support\Facades\Http;

class Zone
{
    /**
     * The zone identifier
     *
     * @var string
     */
    public $id;

    /**
     * Create a new zone instance.
     *
     * @param  string  $id
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Create a new DNS record for the zone.
     *
     * @param  string  $name
     * @param  string  $content
     * @return array
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function createDnsRecord($name, $content)
    {
        $url = config('services.cloudflare.api_url') . "/zones/{$this->id}/dns_records";

        $response = Http::asJson()
            ->withHeaders([
                'X-Auth-Email' => config('services.cloudflare.auth_email'),
                'X-Auth-Key' => config('services.cloudflare.auth_key'),
            ])
            ->post($url, [
                'type' => 'CNAME',
                'name' => $name,
                'content' => $content,
                'ttl' => 1,
                'proxied' => true,
            ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Delete the given DNS record.
     *
     * @param  string  $id
     * @return array
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function deleteDnsRecord($id)
    {
        $url = config('services.cloudflare.api_url') . "/zones/{$this->id}/dns_records/{$id}";

        $response = Http::asJson()
            ->withHeaders([
                'X-Auth-Email' => config('services.cloudflare.auth_email'),
                'X-Auth-Key' => config('services.cloudflare.auth_key'),
            ])
            ->delete($url);

        $response->throw();

        return $response->json();
    }

    /**
     * List a zone's DNS records.
     *
     * @param  array|null  $query
     * @return array
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function listDnsRecords($query = null)
    {
        $url = config('services.cloudflare.api_url') . "/zones/{$this->id}/dns_records";

        $response = Http::asJson()
            ->withHeaders([
                'X-Auth-Email' => config('services.cloudflare.auth_email'),
                'X-Auth-Key' => config('services.cloudflare.auth_key'),
            ])
            ->get($url, $query);

        $response->throw();

        return $response->json();
    }

    /**
     * Patch a zone's DNS records.
     *
     * @param  string  $id
     * @param  array  $data
     * @return array
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function patchDnsRecord($id, $data)
    {
        $url = config('services.cloudflare.api_url') . "/zones/{$this->id}/dns_records/{$id}";

        $response = Http::asJson()
            ->withHeaders([
                'X-Auth-Email' => config('services.cloudflare.auth_email'),
                'X-Auth-Key' => config('services.cloudflare.auth_key'),
            ])
            ->patch($url, $data);

        $response->throw();

        return $response->json();
    }
}
