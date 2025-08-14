<?php

namespace Kdabrow\Filters;

use Illuminate\Database\Eloquent\Builder;

trait HasFilters
{
    /**
     * Apply filters and ordering to the model query
     *
     * @param string|Filter|null $name Filter class name or instance
     * @param array|null $input Filter input array (defaults to request input)
     * @param array|null $order Order input array (defaults to request input)
     * @return Builder
     */
    public function scopeFilter(Builder $query, string|Filter $name = null, ?array $input = null, ?array $order = null): Builder
    {
        // Get filter instance
        $filterInstance = $this->resolveFilter($name);
        
        // Get input data
        $filterInput = $this->resolveInput($input);
        
        // Apply filters if we have input
        if (!empty($filterInput)) {
            $filterInstance->load($filterInput, $this);
            $filterInstance->apply($query);
        }
        
        // Apply ordering
        $orderInput = $this->resolveOrderInput($order);
        if (!empty($orderInput)) {
            $ordering = new Ordering();
            $ordering->load($orderInput, $this);
            $ordering->apply($query);
        }
        
        return $query;
    }
    
    /**
     * Resolve the filter instance
     */
    protected function resolveFilter(string|Filter $name = null): Filter
    {
        // If a Filter instance is provided, use it directly
        if ($name instanceof Filter) {
            return $name;
        }
        
        // If a class name is provided, instantiate it
        if (is_string($name) && class_exists($name)) {
            return new $name();
        }
        
        // Try to resolve a default filter for this model
        $defaultFilterClass = $this->getDefaultFilterClass();
        if ($defaultFilterClass && class_exists($defaultFilterClass)) {
            return new $defaultFilterClass();
        }
        
        // Fall back to the base Filter class
        return new Filter();
    }
    
    /**
     * Resolve the input data
     */
    protected function resolveInput(?array $input = null): array
    {
        if ($input !== null) {
            return $input;
        }
        
        // Try to get from request if available
        try {
            if (function_exists('request') && request()) {
                $configKey = function_exists('config') ? config('filters.key', 'f') : 'f';
                return request()->input($configKey, []);
            }
        } catch (\Exception $e) {
            // Ignore exceptions when Laravel context is not available
        }
        
        return [];
    }
    
    /**
     * Resolve the order input data
     */
    protected function resolveOrderInput(?array $order = null): array
    {
        if ($order !== null) {
            return $order;
        }
        
        // Try to get from request if available
        try {
            if (function_exists('request') && request()) {
                $configKey = function_exists('config') ? config('filters.order_key', 'o') : 'o';
                return request()->input($configKey, []);
            }
        } catch (\Exception $e) {
            // Ignore exceptions when Laravel context is not available
        }
        
        return [];
    }
    
    /**
     * Get the default filter class for this model
     */
    protected function getDefaultFilterClass(): ?string
    {
        try {
            $autoDiscovery = function_exists('config') ? config('filters.auto_discovery', true) : true;
            if (!$autoDiscovery) {
                return null;
            }
        } catch (\Exception $e) {
            // Default to true if config is not available
        }
        
        $modelName = class_basename($this);
        
        // Try configured namespace first
        try {
            $namespace = function_exists('config') ? config('filters.namespace', 'App\\Filters') : 'App\\Filters';
        } catch (\Exception $e) {
            $namespace = 'App\\Filters';
        }
        
        $filterClass = "{$namespace}\\{$modelName}Filter";
        
        if (class_exists($filterClass)) {
            return $filterClass;
        }
        
        // Try in the same namespace as the model
        $modelClass = get_class($this);
        $modelNamespace = dirname(str_replace('\\', '/', $modelClass));
        $filterClass = str_replace('/', '\\', $modelNamespace) . "\\Filters\\{$modelName}Filter";
        
        if (class_exists($filterClass)) {
            return $filterClass;
        }
        
        return null;
    }
}