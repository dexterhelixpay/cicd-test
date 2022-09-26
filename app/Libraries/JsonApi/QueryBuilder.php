<?php

namespace App\Libraries\JsonApi;

use App\Libraries\JsonApi\Concerns\FiltersResources;
use App\Libraries\JsonApi\Concerns\IncludesResources;
use App\Libraries\JsonApi\Concerns\SelectsFields;
use App\Libraries\JsonApi\Concerns\SortsFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request as BaseRequest;
use Illuminate\Support\Str;

class QueryBuilder extends Builder
{
    use FiltersResources, IncludesResources, SelectsFields, SortsFields;

    /**
     * The request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * The resource name.
     *
     * @var string
     */
    protected $resourceName;

    /**
     * Create a new builder instance.
     *
     * @param  Builder|\Illuminate\Database\Eloquent\Relations\Relation  $builder
     * @param  string|null  $resourceName
     * @param  string|null  $resourceName
     * @param  BaseRequest|null  $request
     */
    public function __construct($builder, $resourceName = null, $request = null)
    {
        parent::__construct(clone $builder->getQuery());

        $this->resourceName = $resourceName ? Str::snake($resourceName) : null;
        $this->request = Request::from($request ?? request());

        $this->initialize($builder);
    }

    /**
     * Create a new builder instance for the model.
     *
     * @param  Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @param  string|null  $resourceName
     * @return static
     */
    public static function for($query, $resourceName = null)
    {
        return new static(is_string($query) ? $query::query() : $query, $resourceName);
    }

    /**
     * Apply request filters, includes, and sorting to this query builder.
     *
     * @return $this
     */
    public function apply()
    {
        return $this
            ->selectFields($this->request, $this->resourceName)
            ->filterResources($this->request)
            ->sortFields($this->request)
            ->includeResources($this->request);
    }

    /**
     * Execute the query.
     *
     * @param  bool  $forcePaginate
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function fetch($forcePaginate = false)
    {
        $page = $this->request->page();

        if ($page->count() === 0) {
            if (!$forcePaginate) {
                return $this->get();
            }

            $page->put('size', 10);
        }

        if ($page->has('size')) {
            $perPage = (int) min($page->get('size'), 100);
        }

        if ($page->has('number')) {
            $pageNumber = (int) $page->get('number');
        }

        return $this->paginate(
            isset($perPage) ? $perPage : null,
            ['*'],
            'page',
            isset($pageNumber) ? $pageNumber : null
        );
    }

    /**
     * Set the model, eager loads, scopes, and local macros to the query builder.
     *
     * @param  Builder  $builder
     * @return void
     */
    protected function initialize(Builder $builder)
    {
        $this
            ->setModel($builder->getModel())
            ->setEagerLoads($builder->getEagerLoads());

        $builder->macro('getProtectedProperty', function (Builder $builder, $property) {
            return $builder->{$property};
        });

        $this->scopes = $builder->getProtectedProperty('scopes');
        $this->localMacros = $builder->getProtectedProperty('localMacros');
        $this->onDelete = $builder->getProtectedProperty('onDelete');
    }
}
