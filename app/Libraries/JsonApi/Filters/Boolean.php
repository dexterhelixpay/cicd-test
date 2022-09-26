<?php

namespace App\Libraries\JsonApi\Filters;

class Boolean extends Filter
{
    /**
     * @param  mixed  $value
     * @param  bool  $not
     * @param  string|null  $clause
     * @return array
     */
    protected function equalTo($value, bool $not = false, ?string $clause = null)
    {
        $keys = $not ? ['ne', 'not_equals'] : ['eq', 'equals'];

        if (is_null($value = $this->getFilterValue($value, $keys, !$not))) {
            return [];
        }

        return [$this->clause => [
            [$this->column, $not ? '<>' : '=', $this->castValue($value)],
        ]];
    }

    /**
     * Cast the value to the given type.
     *
     * @param  mixed  $value
     * @param  string}null  $type
     * @return mixed
     */
    protected function castValue($value, $type = null)
    {
        return is_null($value)
            ? $value
            : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
