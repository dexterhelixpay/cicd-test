<?php

namespace App\Libraries\PayMaya;

use App\Facades\PayMaya;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;

class Customer
{
    /**
     * The customer ID.
     *
     * @var string
     */
    public $id;

    /**
     * Create a new PayMaya customer instance.
     *
     * @param  string  $id
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Get the customer's linked cards.
     *
     * @return array
     */
    public function getCards()
    {
        return Card::get($this->id);
    }

    /**
     * Link the given card to the customer.
     *
     * @param  string  $cardTokenId
     * @param  bool  $isDefault
     * @return array
     */
    public function linkCard($cardTokenId, $isDefault = false)
    {
        return Card::link($this->id, $cardTokenId, $isDefault);
    }

    /**
     * Pay using the given customer's card.
     *
     * @param  string  $cardId
     * @param  float  $amount
     * @param  array  $metadata
     * @param  array  $parameters
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function payWithCard($cardId, $amount, $metadata = [], $parameters = [])
    {
        return (new Card($this->id, $cardId))->pay($amount, $metadata, $parameters);
    }

    /**
     * Create a customer using the given data.
     *
     * @param  array  $data
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function create($data)
    {
        try {
            $response = PayMaya::payments(true)
                ->post('customers', ['json' => $data]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Delete the customer with the given ID.
     *
     * @param  string  $id
     * @return array
     */
    public static function delete($id)
    {
        try {
            $response = PayMaya::payments(true)
                ->delete("customers/{$id}");

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Find the customer with the given ID.
     *
     * @param  string  $id
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function find($id)
    {
        try {
            $response = PayMaya::payments(true)->get("customers/{$id}");

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Find the customer with the given ID or throw an exception.
     *
     * @param  string  $id
     * @return self
     */
    public static function findOrFail(string $id)
    {
        PayMaya::payments(true)->get("customers/{$id}");

        return new static($id);
    }

    /**
     * Find the customer's card with the given ID.
     *
     * @param  string  $cardId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function findCard($cardId)
    {
        try {
            $response = PayMaya::payments(true)
                ->get("customers/{$this->id}/cards/{$cardId}");

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Update the customer's data.
     *
     * @param  array  $data
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function update($data)
    {
        try {
            $response = PayMaya::payments(true)
                ->put("customers/{$this->id}", ['json' => $data]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Update or create the customer if the given ID doesn't exist.
     *
     * @param  mixed  $id
     * @param  array  $data
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function updateOrCreate($id, $data)
    {
        if (!$id) {
            return self::create($data);
        }

        return self::find($id)
            ->then(function ($customer) use ($data) {
                return (new static($customer['id']))->update($data);
            }, function ($e) use ($data) {
                return self::create($data);
            });
    }
}
