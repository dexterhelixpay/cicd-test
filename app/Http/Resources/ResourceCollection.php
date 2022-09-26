<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection as BaseResourceCollection;
use Illuminate\Support\Collection;

class ResourceCollection extends BaseResourceCollection
{
    /**
     * Create a new resource instance.
     *
     * @param  mixed  $resource
     * @return void
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'data' => Resource::collection($this->resource),
        ];
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
                    return new Resource($resource);
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
        $response
            ->header('Content-Type', 'application/vnd.api+json')
            ->header('X-Result-Count', $this->count());

        if (method_exists($this->resource, 'total')) {
            $response->header('X-Result-Total', $this->resource->total());
        }
    }

    /**
     * Get the relationships of the resource collection.
     *
     * @return array
     */
    protected function getIncludedResources()
    {
        return $this->resource
            ->map(function ($resource) {
                return (new Resource($resource))->getIncludedResources();
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
}
