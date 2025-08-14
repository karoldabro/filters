# Laravel Filters Usage Examples

## Quick Start with 'f' Parameter

### Simple Request Examples

```bash
# Basic equality filter
GET /users?f[0][c]=name&f[0][o]==&f[0][v]=John

# Like search
GET /users?f[0][c]=email&f[0][o]=like&f[0][v]=@gmail.com

# Multiple filters (AND by default)
GET /users?f[0][c]=name&f[0][o]=like&f[0][v]=John&f[1][c]=status&f[1][o]==&f[1][v]=active

# OR condition
GET /users?f[0][c]=role&f[0][o]==&f[0][v]=admin&f[1][c]=role&f[1][o]==&f[1][v]=moderator&f[1][t]=or

# NULL checks (no 'v' parameter needed)
GET /users?f[0][c]=deleted_at&f[0][o]=null

# NOT NULL checks
GET /users?f[0][c]=verified_at&f[0][o]=nnull

# IN operator
GET /users?f[0][c]=status&f[0][o]=in&f[0][v]=active,pending,review
```

### Laravel Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        // This will automatically use the 'f' parameter from the request
        return User::filter()
            ->with('profile')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }
}
```

### Frontend JavaScript Example

```javascript
// Building filter URLs
const filters = [
    { c: 'name', o: 'like', v: 'John' },
    { c: 'status', o: '=', v: 'active' },
    { c: 'verified_at', o: 'nnull' }  // No 'v' needed for null checks
];

// Convert to URL parameters
const params = new URLSearchParams();
filters.forEach((filter, index) => {
    params.append(`f[${index}][c]`, filter.c);
    params.append(`f[${index}][o]`, filter.o);
    if (filter.v !== undefined) {
        params.append(`f[${index}][v]`, filter.v);
    }
    if (filter.t) {
        params.append(`f[${index}][t]`, filter.t);
    }
});

// Result: f[0][c]=name&f[0][o]=like&f[0][v]=John&f[1][c]=status&f[1][o]=%3D&f[1][v]=active&f[2][c]=verified_at&f[2][o]=nnull
const url = `/api/users?${params.toString()}`;
```

## Ordering with 'o' Parameter

### Basic Ordering Examples

```bash
# Single column ordering (default ascending)
GET /users?f[0][c]=name&f[0][o]=like&f[0][v]=John&o[0][c]=name

# Single column descending
GET /users?f[0][c]=name&f[0][o]=like&f[0][v]=John&o[0][c]=created_at&o[0][v]=desc

# Multiple column ordering
GET /users?f[0][c]=status&f[0][o]==&f[0][v]=active&o[0][c]=name&o[0][v]=asc&o[1][c]=created_at&o[1][v]=desc

# Ordering without filtering
GET /users?o[0][c]=name&o[0][v]=asc&o[1][c]=created_at&o[1][v]=desc
```

### Laravel Controller with Ordering

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        // This will automatically use both 'f' (filter) and 'o' (order) parameters from the request
        return User::filter()
            ->with('profile')
            ->paginate(15);
    }
    
    public function searchWithCustomOrder()
    {
        // Custom ordering with filters
        return User::filter(
            input: [['c' => 'status', 'o' => '=', 'v' => 'active']],
            order: [['c' => 'name', 'v' => 'asc'], ['c' => 'created_at', 'v' => 'desc']]
        )->paginate(15);
    }
}
```

### Model with Orderable Interface

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

    protected $fillable = ['name', 'email', 'status', 'role'];

    public function filters(): array
    {
        return [
            'name' => ['allowedOperators' => ['=', 'like']],
            'email' => ['allowedOperators' => ['=', 'like']],
            'status' => ['allowedOperators' => ['=', 'in']],
        ];
    }

    public function orders(): array
    {
        return [
            'name' => ['allowedDirections' => ['asc', 'desc']],
            'email' => ['allowedDirections' => ['asc', 'desc']],
            'created_at' => ['allowedDirections' => ['desc']], // Only desc
            'updated_at' => ['allowedDirections' => ['desc']], // Only desc
        ];
    }
}
```

### Model with Column Aliases

For better API design and security, you can hide real column names using aliases:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kdabrow\Filters\HasFilters;
use Kdabrow\Filters\Filterable;
use Kdabrow\Filters\Orderable;

class Product extends Model implements Filterable, Orderable
{
    use HasFilters;

    protected $fillable = ['product_name', 'price', 'category_id', 'is_active'];

    public function filters(): array
    {
        return [
            // API uses 'name' but database column is 'product_name'
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

### Example API Calls with Aliases

```bash
# Filter by product name (uses product_name column internally)
GET /products?f[0][c]=name&f[0][o]=like&f[0][v]=iPhone

# Filter by category (uses category_id column internally)
GET /products?f[0][c]=category&f[0][o]==&f[0][v]=1

# Order by newest products (uses created_at column internally, only desc allowed)
GET /products?o[0][c]=newest&o[0][v]=desc

# Combined: Active products in electronics category, ordered by price
GET /products?f[0][c]=active&f[0][o]==&f[0][v]=1&f[1][c]=category&f[1][o]==&f[1][v]=2&o[0][c]=price&o[0][v]=asc
```

### Frontend JavaScript with Ordering

```javascript
// Building filter and order URLs
const filters = [
    { c: 'name', o: 'like', v: 'John' },
    { c: 'status', o: '=', v: 'active' }
];

const orders = [
    { c: 'name', v: 'asc' },
    { c: 'created_at', v: 'desc' }
];

// Convert to URL parameters
const params = new URLSearchParams();

// Add filters
filters.forEach((filter, index) => {
    params.append(`f[${index}][c]`, filter.c);
    params.append(`f[${index}][o]`, filter.o);
    if (filter.v !== undefined) {
        params.append(`f[${index}][v]`, filter.v);
    }
});

// Add ordering
orders.forEach((order, index) => {
    params.append(`o[${index}][c]`, order.c);
    if (order.v) {
        params.append(`o[${index}][v]`, order.v);
    }
});

// Result: f[0][c]=name&f[0][o]=like&f[0][v]=John&f[1][c]=status&f[1][o]=%3D&f[1][v]=active&o[0][c]=name&o[0][v]=asc&o[1][c]=created_at&o[1][v]=desc
const url = `/api/users?${params.toString()}`;

// Fetch data
fetch(url)
    .then(response => response.json())
    .then(data => console.log(data));
```

## Nested Conditions with 'f' Parameter

Coming soon in next version - nested array support will work seamlessly with the 'f' parameter for complex grouped conditions.