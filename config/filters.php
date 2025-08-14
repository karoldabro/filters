<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filter Input Key
    |--------------------------------------------------------------------------
    |
    | This is the default key that will be used to extract filter data from
    | the request when using the HasFilters trait with $model->filter().
    | You can override this per request by passing input directly.
    |
    */
    'key' => 'f',

    /*
    |--------------------------------------------------------------------------
    | Default Order Input Key
    |--------------------------------------------------------------------------
    |
    | This is the default key that will be used to extract ordering data from
    | the request when using the HasFilters trait with $model->filter().
    | Each order should have 'c' (column) and optional 'v' (direction).
    |
    */
    'order_key' => 'o',

    /*
    |--------------------------------------------------------------------------
    | Default Filter Namespace
    |--------------------------------------------------------------------------
    |
    | The default namespace where filter classes will be looked for when
    | using automatic filter resolution. The trait will try to find filters
    | in this namespace using the pattern: {Namespace}\{ModelName}Filter
    |
    */
    'namespace' => 'App\\Filters',

    /*
    |--------------------------------------------------------------------------
    | Auto-discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, the HasFilters trait will automatically try to discover
    | and use filter classes based on model names. If disabled, you must
    | explicitly pass the filter class to the filter() method.
    |
    */
    'auto_discovery' => true,
];