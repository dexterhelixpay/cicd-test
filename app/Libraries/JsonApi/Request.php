<?php

namespace App\Libraries\JsonApi;

use Illuminate\Http\Request as BaseRequest;
use Illuminate\Support\Str;

class Request extends BaseRequest
{
    /**
     * Create a new request instance from the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return self
     */
    public static function from(BaseRequest $request): self
    {
        return static::createFrom($request, new self());
    }

    /**
     * Collect the selected fields per table from the request.
     *
     * @return \Illuminate\Support\Collection
     */
    public function fields()
    {
        return collect($this->query('fields', []))
            ->mapWithKeys(function ($fields, $resource) {
                return [Str::snake($resource) => explode(',', $fields)];
            });
    }

    /**
     * Collect the selected fields per table from the request.
     *
     * @return \Illuminate\Support\Collection
     */
    public function filters()
    {
        $filters = $this->query('filter', []);

        if (is_string($filters)) {
            return collect();
        }

        return collect($filters)
            ->map(function ($value) {
                return $this->getFilterValue($value);
            });
    }

    /**
     * Collect the included resources from the request.
     *
     * @return \Illuminate\Support\Collection
     */
    public function includes()
    {
        $includes = $this->query('include', []);

        return collect(is_string($includes) ? explode(',', $includes) : $includes)
            ->filter()
            ->map([Str::class, 'camel']);
    }

    /**
     * Collect the page options from the request.
     *
     * @return \Illuminate\Support\Collection
     */
    public function page()
    {
        return collect(
            is_array($page = $this->query('page', [])) ? $page : []
        )->only('number', 'size');
    }

    /**
     * Collect the included resources from the request.
     *
     * @return \Illuminate\Support\Collection
     */
    public function sorts()
    {
        $sorts = $this->query('sort', []);

        return collect(is_string($sorts) ? explode(',', $sorts) : $sorts)
            ->filter();
    }

    /**
     * Get the appropriate value from the given filter value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getFilterValue($value)
    {
        if (is_array($value)) {
            return collect($value)->map(function ($value) {
                return $this->getFilterValue($value);
            })->all();
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        return $value;
    }
}
