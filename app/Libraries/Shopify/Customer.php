<?php

namespace App\Libraries\Shopify;

class Customer extends Api
{
    /**
     * Create a customer.
     *
     * @param  array  $data
     * @return \Illuminate\Http\Client\Response
     */
    public function create(array $data)
    {
        return $this->client()
            ->post('admin/api/2022-04/customers.json', ['customer' => $data]);
    }

    /**
     * Retrieve a single customer.
     *
     * @param  string|int  $id
     * @param  array  $query
     * @return \Illuminate\Http\Client\Response
     */
    public function find($id, array $query = [])
    {
        return $this->client()
            ->get("admin/api/2022-04/customers/{$id}.json", $query);
    }

    /**
     * Retrieve a list of customers.
     *
     * @param  array  $query
     * @return \Illuminate\Http\Client\Response
     */
    public function get(array $query = [])
    {
        return $this->client()
            ->get('admin/api/2022-04/customers.json', $query);
    }

    /**
     * Search for customers that match the given query.
     *
     * @param  array  $query
     * @return \Illuminate\Http\Client\Response
     */
    public function search(array $query = [])
    {
        return $this->client()
            ->get('admin/api/2022-04/customers/search.json', $query);
    }
}
