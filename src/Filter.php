<?php

namespace Kdabrow\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use function array_filter;
use function explode;
use function get_class_methods;
use function in_array;
use function str_starts_with;
use function strip_tags;
use function trim;

class Filter
{
    protected array $input;
    protected Model $model;
    private array $operators = ['=', '!=', '>', '<', '>=', '<=', 'like', 'nlike', 'in', 'nin', 'null', 'nnull',];
    private array $queryTypes = ['and', 'or'];

    /**
     * Loads the request input
     */
    public function load(array $input, Model $model): self
    {
        $this->input = $input;
        $this->model = $model;

        return $this;
    }

    /**
     * Will apply all methods that start with a 'filter'
     */
    public function apply(Builder|EloquentBuilder $builder): void
    {
        $this->applyModelFilters($builder);

        $this->applyCustomFilters($builder);
    }

    /**
     * Load all filters except those
     */
    protected function except(): array
    {
        return [];
    }

    /**
     * Load only selected filters
     */
    protected function only(): array
    {
        return [];
    }

    private function applyCustomFilters(Builder|EloquentBuilder $builder): void
    {
        $methods = array_filter(get_class_methods($this), function (string $methodName) {
            return str_starts_with($methodName, 'filter');
        });

        if (empty($methods)) {
            return;
        }

        if (!empty($this->except())) {
            $methods = array_diff($methods, $this->except());
        }

        if (!empty($this->only())) {
            $methods = array_intersect($methods, $this->only());
        }

        foreach ($methods as $method) {
            $this->$method($builder);
        }
    }

    private function applyModelFilters(Builder|EloquentBuilder $builder): void
    {
        /** @var array $filters */
        $filters = $this->input ?? [];

        if (empty($filters)) {
            return;
        }

        /**
         * If the model implements the Filterable instance, fields from filters() take precedence and we should
         * filter only by those fields
         */
        if ($this->model instanceof Filterable) {
            $filterConfig = $this->model->filters();
            $fillable = array_keys($filterConfig);
        } else {
            $filterConfig = [];
            $fillable = $this->model->getFillable();
        }

        // Apply filters recursively
        $this->applyFiltersRecursive($builder, $filters, $fillable, true, $filterConfig);
    }

    private function applyFilterToBuilder(Builder|EloquentBuilder $builder, array $filter): void
    {
        $queryType = $filter['t'] ?? 'and';
        $method = $queryType === 'or' ? 'orWhere' : 'where';
        $inMethod = $queryType === 'or' ? 'orWhereIn' : 'whereIn';
        $notInMethod = $queryType === 'or' ? 'orWhereNotIn' : 'whereNotIn';
        $nullMethod = $queryType === 'or' ? 'orWhereNull' : 'whereNull';
        $notNullMethod = $queryType === 'or' ? 'orWhereNotNull' : 'whereNotNull';

        match ($filter['o']) {
            '=', '!=', '>', '<', '>=', '<=' => $builder->$method($filter['c'], $filter['o'], $filter['v']),
            'like' => $builder->$method($filter['c'], 'LIKE', "%{$filter['v']}%"),
            'nlike' => $builder->$method($filter['c'], 'NOT LIKE', "%{$filter['v']}%"),
            'in' => $builder->$inMethod($filter['c'], $this->parseArrayValue($filter['v'])),
            'nin' => $builder->$notInMethod($filter['c'], $this->parseArrayValue($filter['v'])),
            'null' => $builder->$nullMethod($filter['c']),
            'nnull' => $builder->$notNullMethod($filter['c']),
        };
    }

    protected function sanitizeValue(string $value): string
    {
        // Remove HTML tags and trim whitespace
        $value = trim(strip_tags($value));
        
        // Additional security: prevent SQL injection patterns
        return preg_replace('/[\x00\x0A\x0D\x1A\x22\x27\x5C]/', '', $value);
    }

    protected function parseArrayValue(string $value): array
    {
        if (empty($value)) {
            return [];
        }
        
        return array_map('trim', explode(',', $value));
    }

    private function applyFiltersRecursive(Builder|EloquentBuilder $builder, array $filters, array $fillable, bool $isRoot = false, array $filterConfig = []): void
    {
        $firstFilter = true;
        
        foreach ($filters as $key => $filter) {
            // Skip the 't' parameter itself when it appears in the array
            if ($key === 't') {
                continue;
            }
            
            // Check if this is a nested array (group)
            if ($this->isNestedGroup($filter)) {
                $groupType = $filter['t'] ?? ($isRoot && !$firstFilter ? 'and' : 'and');
                
                // Remove 't' from filter array to avoid conflicts
                $groupFilters = array_filter($filter, function($key) {
                    return $key !== 't';
                }, ARRAY_FILTER_USE_KEY);
                
                if ($firstFilter && $isRoot) {
                    $builder->where(function ($query) use ($groupFilters, $fillable, $filterConfig) {
                        $this->applyFiltersRecursive($query, $groupFilters, $fillable, false, $filterConfig);
                    });
                } else {
                    if ($groupType === 'or') {
                        $builder->orWhere(function ($query) use ($groupFilters, $fillable, $filterConfig) {
                            $this->applyFiltersRecursive($query, $groupFilters, $fillable, false, $filterConfig);
                        });
                    } else {
                        $builder->where(function ($query) use ($groupFilters, $fillable, $filterConfig) {
                            $this->applyFiltersRecursive($query, $groupFilters, $fillable, false, $filterConfig);
                        });
                    }
                }
            } else {
                // Regular filter - validate and apply with alias resolution
                $validFilter = $this->validateFilter($filter, $fillable);
                if ($validFilter) {
                    // Resolve alias to actual column name
                    $resolvedFilter = $this->resolveFilterAlias($validFilter, $filterConfig);
                    $this->applyFilterToBuilder($builder, $resolvedFilter);
                }
            }
            
            $firstFilter = false;
        }
    }
    
    private function isNestedGroup(array $filter): bool
    {
        // Check if this array contains other arrays (making it a group)
        foreach ($filter as $key => $value) {
            if ($key !== 't' && is_array($value) && isset($value['c'])) {
                return true;
            }
        }
        return false;
    }
    
    private function validateFilter(array $filter, array $fillable): ?array
    {
        // Validate basic filter structure (column and operator are always required)
        if (!isset($filter['c'], $filter['o'])) {
            return null;
        }
        
        // Check if value is required for this operator
        if (!in_array($filter['o'], ['null', 'nnull']) && !isset($filter['v'])) {
            return null;
        }
        
        // Set default query type
        if (!isset($filter['t'])) {
            $filter['t'] = 'and';
        }
        
        // Validate allowed column names
        if (!in_array($filter['c'], $fillable)) {
            return null;
        }
        
        // Validate allowed operators
        if (!in_array($filter['o'], $this->operators)) {
            return null;
        }
        
        // Validate query type
        if (!in_array($filter['t'], $this->queryTypes)) {
            return null;
        }
        
        // Sanitize input value (only if value is provided)
        if (isset($filter['v'])) {
            $filter['v'] = $this->sanitizeValue($filter['v']);
        }
        
        return $filter;
    }

    /**
     * Resolve filter alias to actual column name
     */
    private function resolveFilterAlias(array $filter, array $filterConfig): array
    {
        $alias = $filter['c'];
        
        // If we have filter config and there's a column mapping for this alias
        if (!empty($filterConfig) && isset($filterConfig[$alias]['column'])) {
            $filter['c'] = $filterConfig[$alias]['column'];
        }
        
        return $filter;
    }
}
