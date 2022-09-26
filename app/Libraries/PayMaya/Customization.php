<?php

namespace App\Libraries\PayMaya;

use App\Facades\PayMaya;
use GuzzleHttp\Exception\ClientException;

class Customization
{
    /**
     * Create or update paymaya checkout UI customizations.
     *
     * @param  array  $data
     * @return array
     */
    public static function updateOrCreate(array $data)
    {
        try {
            $response = PayMaya::checkout(true)
                ->post('customizations', ['json' => $data]);

            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            \Log::info(json_decode($e->getResponse()->getBody(), true));
            \Log::info($e->getTraceAsString());
        }
    }

    /**
     * Delete the paymaya checkout UI customizations.
     *
     * @return array
     */
    public static function delete()
    {
        try {
            $response = PayMaya::payments(true)
                ->delete("customizations");

            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            \Log::info(json_decode($e->getResponse()->getBody(), true));
            \Log::info($e->getTraceAsString());
        }
    }
}
