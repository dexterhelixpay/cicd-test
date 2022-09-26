<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class Model
{
    /**
     * Check if the given model has the given column.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string  $column
     * @return bool
     */
    public static function hasColumn($model, $column)
    {
        $column = trim($column, " \t\n\r\0\x0B.");
        $column = Arr::first(explode('->', $column));
        $column = trim($column, " \t\n\r\0\x0B.");

        if (Str::contains($column, '.')) {
            return static::hasRelatedColumn($model, $column);
        }

        return in_array($column, static::getColumns($model));
    }

    /**
     * Check if the given model's relationship has the given column.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string  $column
     * @return bool
     */
    public static function hasRelatedColumn($model, $column)
    {
        $column = trim(trim($column, '.'));

        [$column, $relation] = collect(explode('.', $column))
            ->pipe(function (Collection $parts) {
                return [
                    Str::snake($parts->pop()),
                    $parts->map([Str::class, 'camel'])->join('.'),
                ];
            });

        return static::hasRelation($model, $relation)
            ? static::hasColumn(static::getRelatedModel($model, $relation), $column)
            : false;
    }

    /**
     * Check whether the given model has the given relation.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string  $relation
     * @return bool
     */
    public static function hasRelation($model, $relation)
    {
        $relation = trim(trim($relation, '.'));

        if (!Str::contains($relation, '.')) {
            return static::getRelationshipKeys($model)
                ->contains(Str::camel($relation));
        }

        $tempModel = $model;

        return collect(explode('.', $relation))
            ->reduce(function ($result, $relationship) use (&$tempModel) {
                if (!$result || !$tempModel) return false;

                $relationship = Str::camel($relationship);
                $relationships = static::getRelationships($tempModel);
                if (!$relationships->has($relationship)) return false;

                $tempModel = $relationships->get($relationship)['model'];

                return true;
            }, true);
    }

    /**
     * Check if the given model has the given column.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string  $column
     * @return array
     */
    public static function getColumns($model, $withType = false)
    {
        $model = is_object($model) ? $model : new $model;
        $table = $model->getTable();

        return Cache::tags('tables'. ($withType ? ':types' : ''))
            ->remember($model->getTable(), 3600, function () use ($model, $table, $withType) {
                return collect(Schema::getColumnListing($table))
                    ->mapWithKeys(function ($column, $index) use ($model, $table) {
                        $type = Schema::getColumnType($table, $column);

                        if ($type === 'boolean' && $model->hasCast($column, 'integer')) {
                            $type = 'integer';
                        }

                        return [$column => $type];
                    })
                    ->when(!$withType, function (Collection $columns) {
                        return $columns->keys();
                    })
                    ->all();
            });
    }

    /**
     * Check if the given model has the given column.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string  $column
     * @return string|bool
     */
    public static function getColumnType($model, $column)
    {
        $columns = static::getColumns($model, true);

        return $columns[$column] ?? false;
    }

    /**
     * Get the related model from the given class and relation.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  string  $relation
     * @return string
     */
    public static function getRelatedModel($model, $relation)
    {
        $relation = trim(trim($relation, '.'));

        return collect(explode('.', $relation))
            ->reduce(function ($model, $relationship) {
                $relationship = Str::camel($relationship);

                return static::getRelationships($model)->get($relationship)['model'];
            }, $model);
    }

    /**
     * Get the relationships of the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @param  bool  $keysOnly
     * @return \Illuminate\Support\Collection
     */
    public static function getRelationships($model, $keysOnly = false)
    {
        $model = is_object($model) ? $model : new $model;
        $reflection = new ReflectionClass($model);

        return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(function (ReflectionMethod $method) {
                return is_subclass_of(
                    optional($method->getReturnType())->getName(),
                    Relation::class
                );
            })
            ->mapWithKeys(function (ReflectionMethod $method, $index) use ($model) {
                $relationship = $method->getName();

                /** @var \ReflectionNamedType */
                $returnType = $method->getReturnType();

                return [
                    $relationship => [
                        'model' => $model->{$relationship}()->getRelated(),
                        'relationship' => $returnType->getName(),
                    ],
                ];
            })
            ->merge($model::$relationships ?? [])
            ->when($keysOnly, function (Collection $relationships) {
                return $relationships->keys();
            });
    }

    /**
     * Get the relationship keys of the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string  $model
     * @return \Illuminate\Support\Collection
     */
    public static function getRelationshipKeys($model)
    {
        return static::getRelationships($model, true);
    }
}
