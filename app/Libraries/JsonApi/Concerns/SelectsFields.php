<?php

namespace App\Libraries\JsonApi\Concerns;

use App\Support\Model;
use Illuminate\Database\Eloquent\Builder;

trait SelectsFields
{
    /**
     * Select the resource fields from the given request.
     *
     * @param  \App\Libraries\JsonApi\Request  $request
     * @param  string|null  $resourceName
     * @return $this
     */
    protected function selectFields($request, $resourceName = null)
    {
        $model = $this->getModel();
        $fields = $request->fields();
        $resourceName = $resourceName ?? $model->getTable();

        return $this
            ->when($fields->has($resourceName), function (Builder $query) use ($model, $fields, $resourceName) {
                $fields = collect($model->getKeyName())
                    ->merge($fields->get($resourceName))
                    ->push($model->getKeyName())
                    ->filter(function ($field) use ($model) {
                        return Model::hasColumn($model, $field);
                    })
                    ->map(function ($field) use ($query) {
                        return $query->qualifyColumn($field);
                    })
                    ->unique();

                return $query->select($fields->all());
            }, function (Builder $query) {
                // TODO: refactor in order to accept aggregate columns
                if ($this->request->has('filter.totalPaidOrders')) return;

                return $query->select($this->getModel()->qualifyColumn('*'));
            });
    }
}
