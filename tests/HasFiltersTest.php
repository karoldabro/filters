<?php

namespace Kdabrow\Filters\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kdabrow\Filters\Filter;
use Kdabrow\Filters\HasFilters;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class HasFiltersTest extends TestCase
{
    public function testHasFiltersTrait(): void
    {
        $model = new TestModelWithFilters();
        
        // Test that the trait is properly included
        $this->assertContains(HasFilters::class, class_uses($model));
    }

    public function testFilterMethodExists(): void
    {
        $model = new TestModelWithFilters();
        $query = $this->createMock(Builder::class);
        
        // Test that the filter scope method exists
        $this->assertTrue(method_exists($model, 'scopeFilter'));
        
        // Test calling with empty input doesn't break anything
        $result = $model->scopeFilter($query, null, []);
        $this->assertSame($query, $result);
    }

    public function testResolveFilterWithClassName(): void
    {
        $model = new TestModelWithFilters();
        
        // Create a reflector to access protected method
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('resolveFilter');
        $method->setAccessible(true);
        
        $filter = $method->invoke($model, TestFilter::class);
        $this->assertInstanceOf(TestFilter::class, $filter);
    }

    public function testResolveFilterWithInstance(): void
    {
        $model = new TestModelWithFilters();
        $filterInstance = new TestFilter();
        
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('resolveFilter');
        $method->setAccessible(true);
        
        $filter = $method->invoke($model, $filterInstance);
        $this->assertSame($filterInstance, $filter);
    }

    public function testResolveFilterWithNull(): void
    {
        $model = new TestModelWithFilters();
        
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('resolveFilter');
        $method->setAccessible(true);
        
        $filter = $method->invoke($model, null);
        $this->assertInstanceOf(Filter::class, $filter);
    }

    public function testResolveInputWithArray(): void
    {
        $model = new TestModelWithFilters();
        
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('resolveInput');
        $method->setAccessible(true);
        
        $input = ['test' => 'data'];
        $result = $method->invoke($model, $input);
        
        $this->assertSame($input, $result);
    }

    public function testResolveInputWithNull(): void
    {
        $model = new TestModelWithFilters();
        
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('resolveInput');
        $method->setAccessible(true);
        
        $result = $method->invoke($model, null);
        
        $this->assertIsArray($result);
    }

    public function testGetDefaultFilterClass(): void
    {
        $model = new TestModelWithFilters();
        
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('getDefaultFilterClass');
        $method->setAccessible(true);
        
        $result = $method->invoke($model);
        
        // Should return null since TestModelWithFiltersFilter doesn't exist
        $this->assertNull($result);
    }

    public function testFilterIntegration(): void
    {
        // Test the basic integration without Laravel Model dependency
        $filters = [
            ['c' => 'name', 'o' => '=', 'v' => 'John'],
            ['c' => 'age', 'o' => '>=', 'v' => '18'],
        ];

        $model = new TestModelWithFilters();
        $query = $this->createMock(Builder::class);

        // Test that the trait can be used successfully
        $this->assertTrue(method_exists($model, 'scopeFilter'));
        
        // The scopeFilter method should return the query builder
        $result = $model->scopeFilter($query, null, []);
        $this->assertSame($query, $result);
    }

    public function testResolveOrderInputReturnsProvidedInput(): void
    {
        $model = new TestModelWithFilters();
        $orderInput = [['c' => 'name', 'v' => 'asc']];
        
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('resolveOrderInput');
        $method->setAccessible(true);
        
        $result = $method->invoke($model, $orderInput);
        
        $this->assertSame($orderInput, $result);
    }

    public function testResolveOrderInputReturnsEmptyArrayWhenNoInput(): void
    {
        $model = new TestModelWithFilters();
        
        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('resolveOrderInput');
        $method->setAccessible(true);
        
        $result = $method->invoke($model, null);
        
        $this->assertSame([], $result);
    }

    public function testOrderingIntegrationBasic(): void
    {
        $model = new TestModelWithFilters();
        $query = $this->createMock(Builder::class);
        
        $orderInput = [
            ['c' => 'name', 'v' => 'asc']
        ];
        
        // Test that the ordering integration works without throwing errors
        $result = $model->scopeFilter($query, null, [], $orderInput);
        $this->assertSame($query, $result);
    }
}

class TestModelWithFilters extends Model
{
    use HasFilters;

    protected $fillable = ['name', 'age', 'email'];
}

class TestFilter extends Filter
{
    // Custom filter for testing
}