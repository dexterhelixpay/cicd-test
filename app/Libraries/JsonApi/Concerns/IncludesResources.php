<?php

namespace App\Libraries\JsonApi\Concerns;

use App\Support\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait IncludesResources
{
    /**
     * Include the resources from the given request.
     *
     * @param  \App\Libraries\JsonApi\Request  $request
     * @return $this
     */
    protected function includeResources($request)
    {
        $model = $this->getModel();

        $includes = $request->includes()
            ->filter(function ($include) use ($model) {
                return Model::hasRelation($model, $include);
            })
            ->pipe(function (Collection $includes) use ($request) {
                return $this->nestIncludes($request, $includes);
            });

        $this->eagerLoadResources($includes);

        return $this;
    }

    /**
     * Eager load the given inclusions into the given builder.
     *
     * @param  \Illuminate\Support\Collection  $includes
     * @param  \Illuminate\Database\Eloquent\Builder|null  $query
     * @return void
     */
    protected function eagerLoadResources($includes, $query = null)
    {
        $query = $query ?? $this;

        $includes->each(function (Collection $include, $clause) use ($query) {
            switch ($clause) {
                case 'select':
                    $fields = collect($query->getModel()->getKeyName())
                        ->merge($include)
                        ->map(function ($field) use ($query) {
                            return $query->qualifyColumn($field);
                        });

                    return $query->select($fields->all());

                case 'with':
                default:
                    $include->each(function ($include, $resource) use ($query) {
                        if (is_numeric($resource)) {
                            return $query->with($include);
                        }

                        $query->with([$resource => function ($query) use ($include) {
                            $this->eagerLoadResources($include, $query);
                        }]);
                    });
            }
        });
    }

    /**
     * Nest the given included relationships.
     *
     * @param  \App\Libraries\JsonApi\Request  $request
     * @param  \Illuminate\Support\Collection  $includes
     * @param  string  $key
     * @return \Illuminate\Support\Collection
     */
    protected function nestIncludes($request, $includes, $key = '')
    {
        return $includes
            ->groupBy(function ($value) {
                preg_match('/^([A-Za-z_]+)/', $value, $matches);

                return $matches[1];
            })
            ->map(function (Collection $includes, $resource) use ($request, $key) {
                $with = collect();

                $includeKey = collect([$key, $resource])->filter()->join('.');
                $fields = collect($request->fields()->get(Str::snake($includeKey)))
                    ->filter(function ($field) use ($includeKey) {
                        return Model::hasRelatedColumn(
                            $this->getModel(), "{$includeKey}.{$field}"
                        );
                    })
                    ->unique();

                if ($fields->count()) {
                    $with = $with->merge(['select' => $fields]);
                }

                $includes = $includes->diff([$resource]);
                if (!$includes->count()) return $with;

                $includes = $includes
                    ->map(function ($include) use ($resource) {
                        return preg_replace('/^'. preg_quote($resource) .'\./', '', $include);
                    });

                return $with
                    ->merge($this->nestIncludes($request, $includes, $includeKey))
                    ->filter();
            })
            ->pipe(function (Collection $includes) {
                $index = 0;
                $includes = $includes
                    ->mapWithKeys(function ($include, $resource) use (&$index) {
                        return count($include)
                            ? [$resource => $include]
                            : [$index++ => $resource];
                    })
                    ->sort();

                return $includes->count()
                    ? collect(['with' => $includes])
                    : collect();
            });
    }
}
