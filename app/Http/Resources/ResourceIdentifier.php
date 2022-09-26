<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ResourceIdentifier extends JsonResource
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
        ];

        if (count($meta = $this->getMeta())) {
            $array['meta'] = $meta;
        }

        return $array;
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
}
