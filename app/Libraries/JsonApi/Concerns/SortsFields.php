<?php

namespace App\Libraries\JsonApi\Concerns;

use App\Support\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Support\Str;

trait SortsFields
{
    /**
     * Add "order by" clauses based on the given request.
     *
     * @param  \App\Libraries\JsonApi\Request  $request
     * @return $this
     */
    protected function sortFields($request)
    {
        $request->sorts()->each(function ($field) {
            $field = str_replace(' ', '', $field);

            $direction = preg_match('/^-(.+)$/', $field, $matches) ? 'desc' : 'asc';
            $column = trim($matches[1] ?? $field, '+-');

            if (Model::hasColumn($this->getModel(), $column)) {
                if (Str::contains($column, '.')) {
                    $this->orderByRelation($column, $direction);
                } else {
                    $this->orderBy($this->qualifyColumn($column), $direction);
                }
            }
        });

        return $this;
    }

    /**
     * Add an "order by" clause on a joined table to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    protected function orderByRelation($column, $direction)
    {
        if (count($parts = explode('.', $column)) !== 2) {
            return $this;
        }

        $relation = $this->getRelation(Str::camel($parts[0]));

        if ($relation instanceof BelongsTo) {
            $this
                ->join(
                    $relation->getModel()->getTable(),
                    $relation->getQualifiedForeignKeyName(),
                    '=',
                    $relation->getQualifiedOwnerKeyName(),
                    'left'
                )
                ->orderBy($relation->getModel()->qualifyColumn($parts[1]), $direction);
        }

        if ($relation instanceof HasOneOrMany) {
            $this
                ->join(
                    $relation->getModel()->getTable(),
                    $relation->getQualifiedParentKeyName(),
                    '=',
                    $relation->getQualifiedForeignKeyName(),
                    'left'
                )
                ->orderBy($relation->getModel()->qualifyColumn($parts[1]), $direction);
        }

        return $this;
    }
}
