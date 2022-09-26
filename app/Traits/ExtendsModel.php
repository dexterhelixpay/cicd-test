<?php

namespace App\Traits;

use Ankurk91\Eloquent\BelongsToOne;
use Ankurk91\Eloquent\MorphToOne;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

trait ExtendsModel
{
    use BelongsToOne, MorphToOne;

    /**
     * The model's relationships.
     *BelongsToOne
     * @var array|null
     */
    // public static $relationships;

    /**
     * Get all relationships of the model.
     *
     * @return array
     */
    public static function getRelationships(): array
    {
        $reflection = new ReflectionClass(static::class);

        return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(function (ReflectionMethod $method) {
                return is_subclass_of(optional($method->getReturnType())->getName(), Relation::class);
            })
            ->mapWithKeys(function (ReflectionMethod $method) {
                return [$method->getName() => $method->getReturnType()->getName()];
            })
            ->merge(static::$relationships ?? [])
            ->all();
    }

    /**
     * Get all relationship keys of the model.
     *
     * @return array
     */
    public static function getRelationshipKeys(): array
    {
        return array_keys((new static)::getRelationships());
    }

    /**
     * Get the model of the specified relation.
     *
     * @param  string  $relation
     * @return mixed
     */
    public function getRelationModel(string $relation): ?Model
    {
        try {
            $relation = Str::camel($relation);

            return $this->{$relation}()->getRelated();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get the info of the specified table column.
     *
     * @param  string  $column
     * @return array|null
     */
    public function getTableColumn($column): ?array
    {
        $columns = $this->getTableColumns();

        return in_array($column, array_keys($columns))
            ? $columns[$column]
            : null;
    }

    /**
     * Get the columns of the model's table.
     *
     * @return array
     */
    public function getTableColumns()
    {
        return Cache::tags('tables')
            ->remember("tables:{$this->getTable()}:columns", 60, function () {
                $table = $this->getTable();

                return collect(Schema::getColumnListing($this->getTable()))
                    ->map(function ($column) use ($table) {
                        return [
                            'name' => $column,
                            'table_key' => "{$table}.{$column}",
                            'type' => Schema::getColumnType($table, $column),
                        ];
                    })
                    ->keyBy('name')
                    ->toArray();
            });
    }

    /**
     * Find a model by its primary key and lock the record.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @param  string  $lock
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public static function lockedFind($id, $columns = ['*'], $lock = 'lockForUpdate')
    {
        return self::query()->whereKey($id)->{$lock}()->first($columns);
    }

    /**
     * Find a model by its primary key and lock the record, or throw an exception.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function lockedFindOrFail($id, $columns = ['*'], $lock = 'lockForUpdate')
    {
        $result = self::lockedFind($id, $columns, $lock);

        if (!is_null($result)) {
            return $result;
        }

        throw (new ModelNotFoundException)->setModel(
            static::class, $id
        );
    }

    /**
     * Silently update the model in the database.
     *
     * @param  array  $attributes
     * @param  array  $options
     * @return bool
     */
    public function silentUpdate(array $attributes = [], array $options = []): bool
    {
        return self::withoutEvents(function () use ($attributes, $options) {
            return $this->update($attributes, $options);
        });
    }

    /**
     * Wrap the model in an array.
     *
     * @return array
     */
    public function wrapToArray(): array
    {
        return [$this];
    }

    /**
     * Wrap the model in a collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function wrapToCollection(): Collection
    {
        return collect($this->wrapToArray());
    }

    /**
     * Get the version of the model.
     *
     * @return int
     */
    public function getModelVersion(): int
    {
        if (preg_match('/\\\v([0-9]+)\\\/', get_class($this), $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    /**
     * Check if the model version and database version is the same.
     *
     * @return bool
     */
    public function isSameVersion(): bool
    {
        if (!array_key_exists('version', $this->getAttributes())) {
            return true;
        }

        return $this->getModelVersion() === intval($this->getAttribute('version'));
    }

    /**
     * Use the specified version of the model.
     *
     * @param  int|null  $version
     * @return self
     */
    public function useModelVersion(int $version = null)
    {
        $class = preg_replace('/\\\v[0-9]+\\\/', '\\', get_class($this));

        if ($version) {
            $parts = explode('\\', $class);
            array_splice($parts, count($parts) - 1, 0, "v{$version}");

            $class = implode('\\', $parts);
        }

        return (new $class)->where($this->getKeyName(), $this->getKey())->first();
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
