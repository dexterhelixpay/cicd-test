<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Resource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $array = [
            'id' => $this->resource->getKey(),
            'type' => $this->resource->getTable(),
            'attributes' => Arr::except($this->resource->attributesToArray(), [
                $this->resource->getKeyName(),
            ]),
        ];

        if (count($relationships = $this->getRelationships($request))) {
            $array['relationships'] = $relationships;
        }

        if (count($meta = $this->getMeta())) {
            $array['meta'] = $meta;
        }

        return $array;
    }

    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        $with = [];

        if (count($included = $this->getIncludedResources())) {
            $with['included'] = collect($included)
                ->sortBy(function ($resource) {
                    return get_class($resource) .':'. $resource->getKey();
                })
                ->map(function ($resource) {
                    return new self($resource);
                })
                ->values()
                ->all();
        }

        return $with;
    }

    /**
     * Customize the response for a request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        $response->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * Get the relationships of the given resource.
     *
     * @param  mixed  $resource
     * @return array
     */
    public function getIncludedResources($resource = null)
    {
        $relations = ($resource ?? $this->resource)->getRelations();

        if (count($relations) === 0) {
            return [];
        }

        return collect($relations)
            ->except('pivot')
            ->filter()
            ->map(function ($resource) {
                return $resource instanceof Collection
                    ? $resource->all()
                    : $resource;
            })
            ->flatten()
            ->map(function ($resource) {
                return collect([$resource])
                    ->concat($this->getIncludedResources($resource))
                    ->all();
            })
            ->flatten()
            ->groupBy(function ($resource) {
                return get_class($resource) .':'. $resource->getKey();
            })
            ->map(function ($resources, $key) {
                [$class] = explode(':', $key);

                return collect($resources)->reduce(function ($carry, $resource) {
                    collect($resource->syncOriginal()->getAttributes())
                        ->each(function ($value, $key) use ($resource, &$carry) {
                            $carry->forceFill([
                                $key => $resource->hasGetMutator($key)
                                    ? $resource->getRawOriginal($key)
                                    : $resource->getOriginal($key),
                            ]);
                        });

                    collect($resource->getRelations())
                        ->each(function ($resource, $relation) use ($carry) {
                            $carry->setRelation(
                                $relation,
                                $carry->relationLoaded($relation) && $resource instanceof Collection
                                    ? $carry->getRelation($relation)->merge($resource)
                                    : $resource
                            );
                        });

                    return $carry;
                }, $class::make());
            })
            ->values()
            ->all();
    }

    /**
     * Get the meta info of the given resource.
     *
     * @return array
     */
    protected function getMeta()
    {
        if (!$this->resource->relationLoaded('pivot')) {
            return [];
        }

        $pivot = $this->resource->getRelation('pivot');

        return Arr::except($pivot->toArray(), [
            $pivot->getRelatedKey(),
            $pivot->getForeignKey(),
        ]);
    }

    /**
     * Get the relationships of the given resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    protected function getRelationships($request)
    {
        if (count($relations = $this->resource->getRelations()) === 0) {
            return [];
        }

        return collect($relations)
            ->except('pivot')
            ->mapWithKeys(function ($resource, $relation) use ($request) {
                if (!$resource) {
                    return [Str::snake($relation) => ['data' => null]];
                }

                return [
                    Str::snake($relation) => $resource instanceof Collection
                        ? (new ResourceIdentifierCollection($resource))->toArray($request)
                        : ['data' => (new ResourceIdentifier($resource))->toArray($request)],
                ];
            })
            ->all();
    }
}
