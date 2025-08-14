<?php

namespace Kdabrow\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Ordering
{
    private array $orders = [];
    private Model $model;

    /**
     * Load ordering data and model
     */
    public function load(array $orders, Model $model): self
    {
        $this->orders = $orders;
        $this->model = $model;
        
        return $this;
    }

    /**
     * Apply ordering to the query builder
     */
    public function apply(Builder $query): Builder
    {
        if (empty($this->orders)) {
            return $query;
        }

        // Get ordering configuration from model if it implements Orderable
        $orderConfig = $this->getOrderConfiguration();

        foreach ($this->orders as $order) {
            if (!$this->isValidOrderEntry($order)) {
                continue;
            }

            $alias = $order['c'];
            $direction = $order['v'] ?? 'asc';

            // Validate column name (security)
            if (!$this->isColumnAllowed($alias, $orderConfig)) {
                continue;
            }

            // Validate and normalize direction (using alias for config lookup)
            $direction = $this->normalizeDirection($direction, $alias, $orderConfig);

            // Resolve alias to actual column name
            $column = $this->resolveColumnAlias($alias, $orderConfig);

            // Sanitize column name
            $column = $this->sanitizeColumnName($column);
            if (empty($column)) {
                continue;
            }

            $query->orderBy($column, $direction);
        }

        return $query;
    }

    /**
     * Check if order entry is valid
     */
    private function isValidOrderEntry($order): bool
    {
        return is_array($order) && isset($order['c']);
    }

    /**
     * Get ordering configuration from model
     */
    private function getOrderConfiguration(): array
    {
        if ($this->model instanceof Orderable) {
            return $this->model->orders();
        }

        // Fallback to fillable attributes if model doesn't implement Orderable
        $fillable = $this->model->getFillable();
        if (empty($fillable)) {
            $fillable = ['*']; // Allow all columns if fillable is empty
        }

        // Convert fillable to order configuration format
        $config = [];
        foreach ($fillable as $column) {
            if ($column === '*') {
                // Wildcard means any column with default directions
                return ['*' => ['allowedDirections' => ['asc', 'desc']]];
            }
            $config[$column] = ['allowedDirections' => ['asc', 'desc']];
        }

        return $config;
    }

    /**
     * Check if column is allowed based on order configuration
     */
    private function isColumnAllowed(string $column, array $orderConfig): bool
    {
        // Check for wildcard
        if (isset($orderConfig['*'])) {
            return true;
        }

        return array_key_exists($column, $orderConfig);
    }

    /**
     * Normalize and validate direction based on column configuration
     */
    private function normalizeDirection(string $direction, string $column, array $orderConfig): string
    {
        $direction = strtolower($direction);
        
        // Get allowed directions for this column
        $allowedDirections = ['asc', 'desc']; // Default
        
        if (isset($orderConfig['*'])) {
            $allowedDirections = $orderConfig['*']['allowedDirections'] ?? ['asc', 'desc'];
        } elseif (isset($orderConfig[$column])) {
            $allowedDirections = $orderConfig[$column]['allowedDirections'] ?? ['asc', 'desc'];
        }

        // If the requested direction is allowed, use it
        if (in_array($direction, $allowedDirections)) {
            return $direction;
        }
        
        // Otherwise, use the first allowed direction as default
        return $allowedDirections[0] ?? 'asc';
    }

    /**
     * Resolve column alias to actual column name
     */
    private function resolveColumnAlias(string $alias, array $orderConfig): string
    {
        // If we have order config and there's a column mapping for this alias
        if (!empty($orderConfig) && isset($orderConfig[$alias]['column'])) {
            return $orderConfig[$alias]['column'];
        }
        
        // Check wildcard config
        if (!empty($orderConfig) && isset($orderConfig['*']['column'])) {
            return $orderConfig['*']['column'];
        }
        
        return $alias;
    }

    /**
     * Sanitize column name to prevent SQL injection
     */
    private function sanitizeColumnName(string $column): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    }
}