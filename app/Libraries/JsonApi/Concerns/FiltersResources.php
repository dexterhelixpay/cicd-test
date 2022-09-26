<?php

namespace App\Libraries\JsonApi\Concerns;

use App\Libraries\JsonApi\Filters\Filter;
use App\Support\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

trait FiltersResources
{
    /**
     * Select the resource fields from the given request.
     *
     * @param  \App\Libraries\JsonApi\Request  $request
     * @return $this
     */
    protected function filterResources($request)
    {
        $filters = $request->filters()
            ->filter(function ($value, $key) {
                return Model::hasColumn($this->getModel(), $key);
            })
            ->pipe(function ($filters) {
                return $this->groupFilters($filters);
            });

        $this->applyFilters($filters);

        return $this;
    }

    /**
     * Apply the given clause filters to the given builder.
     *
     * @param  \Illuminate\Support\Collection  $clauseFilters
     * @param  \Illuminate\Database\Eloquent\Builder|null  $query
     * @return void
     */
    protected function applyFilters($clauseFilters, $query = null)
    {
        $query = $query ?? $this;

        $clauseFilters->each(function (Collection $filters, $clause) use ($query) {
            switch ($clause) {
                case 'whereHas':
                    return $filters->each(function ($clauseFilters, $relation) use ($query) {
                        $query->whereHas($relation, function ($query) use ($clauseFilters) {
                            return $this->applyFilters($clauseFilters, $query);
                        });
                    });

                case 'where':
                default:
                    $filters->each(function ($value, $column) use ($query) {
                        $filters = Filter::for($query->getModel(), $column)($value, $query);

                        foreach ($filters as $clause => $values) {
                            foreach ($values as $value) {
                                $query->{$clause}(...$value);
                            }
                        }
                    });
            }
        });
    }

    /**
     * Group the given filters by column/relationship.
     *
     * @param  \Illuminate\Support\Collection  $filters
     * @return \Illuminate\Support\Collection
     */
    protected function groupFilters($filters)
    {
        return $filters
            ->groupBy(function ($value, $key) {
                return preg_match('/^([A-Za-z_]+)\./', $key, $matches)
                    ? 'whereHas'
                    : 'where';
            }, true)
            ->map(function (Collection $value, $key) {
                if ($key === 'whereHas') {
                    return $value
                        ->groupBy(function ($value, $key) {
                            preg_match('/^([A-Za-z_]+)\./', $key, $matches);

                            return Str::camel($matches[1]);
                        }, true)
                        ->map(function (Collection $value) {
                            return $value->mapWithKeys(function ($value, $key) {
                                return [preg_replace('/^[A-Za-z_]+\./', '', $key) => $value];
                            });
                        })
                        ->map(function (Collection $value) {
                            return $this->groupFilters($value);
                        });
                }

                return $value;
            });
    }
}
