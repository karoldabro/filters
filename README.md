# Laravel Filters

A powerful filtering package for Laravel Eloquent models with support for dynamic queries, custom filters, nested conditions, ordering, and column aliases.

## Features

- **Laravel Model Integration**: Direct filtering with `$model->filter()`
- **URL-based Filtering**: `GET /users?f[0][c]=name&f[0][o]=like&f[0][v]=John`
- **Ordering Support**: `GET /users?o[0][c]=created_at&o[0][v]=desc`
- **Column Aliases**: Hide real database columns from your API
- **Advanced Operators**: `=`, `!=`, `>`, `<`, `>=`, `<=`, `like`, `nlike`, `in`, `nin`, `null`, `nnull`
- **Nested Conditions**: Complex grouping with AND/OR logic
- **Security**: Built-in input sanitization and SQL injection prevention
- **Model Interfaces**: `Filterable` and `Orderable` for fine-grained control

## Installation

```bash
composer require kdabrow/filters
```

The package includes Laravel integration out of the box. The service provider is auto-discovered.

#### Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=filters-config
```

## Quick Start

Add the trait to your model and start filtering:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kdabrow\Filters\HasFilters;

class User extends Model
{
    use HasFilters;

    protected $fillable = ['name', 'email', 'status'];
}
```

```php
// In your controller
class UserController extends Controller
{
    public function index()
    {
        // Automatically filters from request parameters
        return User::filter()
            ->with('profile')
            ->paginate(15);
    }
}
```

**URL Examples:**
```bash
GET /users?f[0][c]=name&f[0][o]=like&f[0][v]=John
GET /users?f[0][c]=status&f[0][o]==&f[0][v]=active&o[0][c]=created_at&o[0][v]=desc
```

## Model Configuration

### Basic Model Setup

The simplest setup uses the `$fillable` array for allowed columns:

```php
class User extends Model
{
    use HasFilters;
    
    protected $fillable = ['name', 'email', 'status', 'created_at'];
    // All fillable columns are available for filtering and ordering
}
```

### Advanced Model Configuration

For fine-grained control, implement the `Filterable` and `Orderable` interfaces:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kdabrow\Filters\HasFilters;
use Kdabrow\Filters\Filterable;
use Kdabrow\Filters\Orderable;

class User extends Model implements Filterable, Orderable
{
    use HasFilters;

    protected $fillable = ['name', 'email', 'status'];

    public function filters(): array
    {
        return [
            'name' => ['allowedOperators' => ['=', 'like']],
            'email' => ['allowedOperators' => ['=', 'like']],
            'status' => ['allowedOperators' => ['=', 'in']],
            'created_at' => ['allowedOperators' => ['>=', '<=']],
        ];
    }

    public function orders(): array
    {
        return [
            'name' => ['allowedDirections' => ['asc', 'desc']],
            'email' => ['allowedDirections' => ['asc', 'desc']],
            'created_at' => ['allowedDirections' => ['desc']], // Only descending
        ];
    }
}
```

### Column Aliases

Hide real database column names from your API using aliases:

```php
class Product extends Model implements Filterable, Orderable
{
    use HasFilters;

    protected $fillable = ['product_name', 'price', 'category_id', 'is_active'];

    public function filters(): array
    {
        return [
            // API alias => Real database column
            'name' => ['allowedOperators' => ['=', 'like'], 'column' => 'product_name'],
            'price' => ['allowedOperators' => ['=', '>=', '<=']],
            'category' => ['allowedOperators' => ['=', 'in'], 'column' => 'category_id'],
            'active' => ['allowedOperators' => ['='], 'column' => 'is_active'],
        ];
    }

    public function orders(): array
    {
        return [
            'name' => ['allowedDirections' => ['asc', 'desc'], 'column' => 'product_name'],
            'price' => ['allowedDirections' => ['asc', 'desc']],
            'newest' => ['allowedDirections' => ['desc'], 'column' => 'created_at'],
        ];
    }
}
```

**API Usage with Aliases:**
```bash
# Filter by 'name' (maps to product_name column)
GET /products?f[0][c]=name&f[0][o]=like&f[0][v]=iPhone

# Order by 'newest' (maps to created_at column)
GET /products?o[0][c]=newest&o[0][v]=desc
```

## Controller Usage

### Basic Usage

```php
class UserController extends Controller
{
    public function index()
    {
        // Automatically uses request parameters 'f' (filters) and 'o' (orders)
        return User::filter()->paginate(15);
    }
}
```

### Manual Parameters

```php
public function customFilter()
{
    $users = User::filter(
        input: [['c' => 'status', 'o' => '=', 'v' => 'active']],
        order: [['c' => 'created_at', 'v' => 'desc']]
    )->get();
    
    return $users;
}
```

### Using Custom Filter Classes

```php
public function advancedFilter()
{
    return User::filter(name: UserFilter::class)->get();
}
```

## Supported Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `=` | Equal to | `['c' => 'status', 'o' => '=', 'v' => 'active']` |
| `!=` | Not equal to | `['c' => 'status', 'o' => '!=', 'v' => 'inactive']` |
| `>` | Greater than | `['c' => 'age', 'o' => '>', 'v' => '18']` |
| `<` | Less than | `['c' => 'age', 'o' => '<', 'v' => '65']` |
| `>=` | Greater than or equal | `['c' => 'price', 'o' => '>=', 'v' => '100']` |
| `<=` | Less than or equal | `['c' => 'price', 'o' => '<=', 'v' => '500']` |
| `like` | Contains (LIKE %value%) | `['c' => 'name', 'o' => 'like', 'v' => 'john']` |
| `nlike` | Does not contain | `['c' => 'email', 'o' => 'nlike', 'v' => 'temp']` |
| `in` | In array | `['c' => 'status', 'o' => 'in', 'v' => 'active,pending']` |
| `nin` | Not in array | `['c' => 'role', 'o' => 'nin', 'v' => 'banned,suspended']` |
| `null` | Is NULL | `['c' => 'deleted_at', 'o' => 'null']` |
| `nnull` | Is NOT NULL | `['c' => 'email_verified_at', 'o' => 'nnull']` |

## URL Structure

### Filter Parameters (f)

```bash
# Basic filter
GET /users?f[0][c]=name&f[0][o]==&f[0][v]=John

# Multiple filters (AND by default)
GET /users?f[0][c]=name&f[0][o]=like&f[0][v]=John&f[1][c]=status&f[1][o]==&f[1][v]=active

# OR condition
GET /users?f[0][c]=role&f[0][o]==&f[0][v]=admin&f[1][c]=role&f[1][o]==&f[1][v]=moderator&f[1][t]=or
```

### Order Parameters (o)

```bash
# Single column ascending (default)
GET /users?o[0][c]=name

# Single column descending
GET /users?o[0][c]=created_at&o[0][v]=desc

# Multiple columns
GET /users?o[0][c]=name&o[0][v]=asc&o[1][c]=created_at&o[1][v]=desc
```

## Advanced Features

### Nested Conditions

Create complex grouped conditions:

```php
$filters = [
    [
        // Group 1: (status=0 AND name='test' OR name='test 2')
        ['c' => 'status', 'o' => '=', 'v' => '0'],
        ['c' => 'name', 'o' => '=', 'v' => 'test'],
        ['c' => 'name', 'o' => '=', 'v' => 'test 2', 't' => 'or'],
    ],
    [
        // Group 2: (status=1 AND name='test 3')
        ['c' => 'status', 'o' => '=', 'v' => '1'],
        ['c' => 'name', 'o' => '=', 'v' => 'test 3'],
        't' => 'or'  // Connect this group with OR
    ]
];

User::filter(input: $filters)->get();
```

**Generated SQL:**
```sql
WHERE (status = '0' AND name = 'test' OR name = 'test 2') 
   OR (status = '1' AND name = 'test 3')
```

### Custom Filter Classes

Create custom filter classes for complex filtering logic that goes beyond simple column comparisons:

```php
<?php

namespace App\Filters;

use Kdabrow\Filters\Filter;

class UserFilter extends Filter
{
    protected function filterActive($builder)
    {
        if (! reqeust()->has('is_verified')) {
            return;
        }
        
        $builder->where('status', 'active')
                ->whereNotNull('verified_at');
    }
    
    protected function filterAdmins($builder)
    {
        if (! reqeust()->has('is_admin')) {
            return;
        }
        
        $builder->whereIn('role', ['admin', 'super_admin']);
    }
    
    protected function filterPremium($builder)
    {
        if (! reqeust()->has('is_premium')) {
            return;
        }
        
        $builder->whereHas('subscription', function($query) {
            $query->where('type', 'premium')
                  ->where('expires_at', '>', now());
        });
    }
}
```

**Auto-Discovery Usage:**

The package automatically discovers filter classes based on your model name:

```php
// For User model, it looks for App\Filters\UserFilter
User::filter()->get();
```

**Manual Class Specification:**

```php
// Explicitly specify the filter class
User::filter(name: UserFilter::class)->get();

// Use a different filter class than the auto-discovered one
User::filter(name: AdminUserFilter::class)->get();

// Pass an instance instead of class name
$filter = new UserFilter();
User::filter(name: $filter)->get();
```

**How Auto-Discovery Works:**

1. Takes your model class name (e.g., `User`, `BlogPost`)
2. Prepends the configured namespace (default: `App\Filters`)  
3. Appends `Filter` suffix
4. Checks if the class exists and extends the base `Filter` class
5. If found, instantiates and uses it; otherwise falls back to dynamic filtering

**Filter Methods:**

- Method names must start with `filter` (e.g., `filterActive`, `filterPremium`)
- Each method receives the query `$builder` as parameter
- Methods are automatically called when the filter is applied

## Configuration

The `config/filters.php` file allows customization:

```php
return [
    // Request parameter key for filters (default: 'f')
    'key' => 'f',
    
    // Request parameter key for ordering (default: 'o')
    'order_key' => 'o',
    
    // Default namespace for filter classes  
    'namespace' => 'App\\Filters',
    
    // Enable/disable auto-discovery of filter classes
    'auto_discovery' => true,
];
```

## Security Features

- **Column Validation**: Only defined columns (fillable or interface) are allowed
- **Operator Validation**: Only specified operators per column are accepted
- **Direction Validation**: Only allowed directions per column are accepted
- **Input Sanitization**: All values are sanitized to prevent SQL injection
- **Column Sanitization**: Column names are sanitized to prevent injection

## Manual Usage (Without Models)

For non-Laravel projects or custom usage:

```php
use Kdabrow\Filters\Filter;
use Kdabrow\Filters\Ordering;

$filters = [
    ['c' => 'name', 'o' => 'like', 'v' => 'John'],
    ['c' => 'status', 'o' => '=', 'v' => 'active'],
];

$orders = [
    ['c' => 'created_at', 'v' => 'desc']
];

// Apply filters
$filter = new Filter();
$filter->load($filters, $model);
$filter->apply($queryBuilder);

// Apply ordering
$ordering = new Ordering();
$ordering->load($orders, $model);
$ordering->apply($queryBuilder);
```

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return User::filter()
            ->with(['posts', 'profile'])
            ->whereHas('posts', function ($query) {
                $query->where('published', true);
            })
            ->paginate(request('per_page', 15));
    }
}
```

**Request Examples:**
```bash
# Search active users named John, ordered by creation date
GET /users?f[0][c]=name&f[0][o]=like&f[0][v]=John&f[1][c]=status&f[1][o]==&f[1][v]=active&o[0][c]=created_at&o[0][v]=desc

# Complex nested condition with ordering
GET /users?f[0][0][c]=role&f[0][0][o]==&f[0][0][v]=admin&f[0][1][c]=status&f[0][1][o]==&f[0][1][v]=active&f[1][0][c]=role&f[1][0][o]==&f[1][0][v]=user&f[1][t]=or&o[0][c]=name&o[0][v]=asc
```