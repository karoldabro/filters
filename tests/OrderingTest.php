<?php

namespace Kdabrow\Filters\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kdabrow\Filters\Ordering;
use Kdabrow\Filters\Orderable;
use PHPUnit\Framework\TestCase;

class OrderingTest extends TestCase
{
    private Ordering $ordering;
    private $mockModel;
    private $mockBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ordering = new Ordering();
        $this->mockModel = $this->createMock(Model::class);
        $this->mockBuilder = $this->createMock(MockableBuilder::class);
        
        $this->mockModel->method('getFillable')->willReturn(['name', 'email', 'age']);
    }

    public function testLoadReturnsInstance(): void
    {
        $orders = [['c' => 'name', 'v' => 'asc']];
        
        $result = $this->ordering->load($orders, $this->mockModel);
        
        $this->assertSame($this->ordering, $result);
    }

    public function testApplyWithEmptyOrdersReturnsBuilder(): void
    {
        $this->ordering->load([], $this->mockModel);
        
        // Should not call orderBy when no orders
        $this->mockBuilder->expects($this->never())
            ->method('orderBy');
        
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplyWithValidSingleOrder(): void
    {
        $orders = [['c' => 'name', 'v' => 'desc']];
        
        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('name', 'desc')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $this->mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplyWithMultipleOrders(): void
    {
        $orders = [
            ['c' => 'name', 'v' => 'asc'],
            ['c' => 'age', 'v' => 'desc']
        ];
        
        $this->mockBuilder->expects($this->exactly(2))
            ->method('orderBy')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $this->mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplyWithDefaultDirection(): void
    {
        $orders = [['c' => 'name']]; // No 'v' parameter
        
        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('name', 'asc')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $this->mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplyIgnoresInvalidColumns(): void
    {
        $orders = [
            ['c' => 'password', 'v' => 'asc'], // Not in fillable
            ['c' => 'name', 'v' => 'desc']     // In fillable
        ];
        
        // Should only call orderBy once for the valid column
        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('name', 'desc')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $this->mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplyWithWildcardFillable(): void
    {
        // Create a fresh mock for this test
        $mockModel = $this->createMock(Model::class);
        $mockModel->method('getFillable')->willReturn(['*']);
        
        $orders = [['c' => 'any_column', 'v' => 'asc']];
        
        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('any_column', 'asc')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplyWithEmptyFillable(): void
    {
        // Create a fresh mock for this test
        $mockModel = $this->createMock(Model::class);
        $mockModel->method('getFillable')->willReturn([]);
        
        $orders = [['c' => 'any_column', 'v' => 'asc']];
        
        // Empty fillable should allow all columns (gets converted to ['*'])
        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('any_column', 'asc')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplySanitizesColumnNames(): void
    {
        // Use wildcard to allow any column name
        $mockModel = $this->createMock(Model::class);
        $mockModel->method('getFillable')->willReturn(['*']);
        
        $orders = [['c' => 'name; DROP TABLE users;', 'v' => 'asc']];
        
        // Should sanitize the column name
        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('nameDROPTABLEusers', 'asc')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplyWithInvalidDirection(): void
    {
        $orders = [['c' => 'name', 'v' => 'invalid']];
        
        // Should default to 'asc' for invalid direction
        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('name', 'asc')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $this->mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplyIgnoresMalformedEntries(): void
    {
        $orders = [
            'not_an_array',                // Not an array
            ['v' => 'asc'],                // Missing 'c'
            ['c' => 'name', 'v' => 'asc']  // Valid
        ];
        
        // Should only call orderBy once for the valid entry
        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('name', 'asc')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $this->mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testApplyIgnoresEmptyColumnName(): void
    {
        $orders = [['c' => '!@#$%', 'v' => 'asc']]; // Will be empty after sanitization
        
        // Should not call orderBy for empty column name
        $this->mockBuilder->expects($this->never())
            ->method('orderBy');
            
        $this->ordering->load($orders, $this->mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testDirectionNormalization(): void
    {
        $orders = [
            ['c' => 'name', 'v' => 'DESC'],     // Upper case
            ['c' => 'email', 'v' => 'AsC'],     // Mixed case
            ['c' => 'age', 'v' => 'desc']       // Lower case
        ];
        
        $this->mockBuilder->expects($this->exactly(3))
            ->method('orderBy')
            ->willReturn($this->mockBuilder);
            
        $this->ordering->load($orders, $this->mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testComplexScenarioIntegration(): void
    {
        // Use wildcard to allow all columns for this complex test
        $mockModel = $this->createMock(Model::class);
        $mockModel->method('getFillable')->willReturn(['*']);
        
        $orders = [
            ['c' => 'name', 'v' => 'desc'],
            ['c' => 'password', 'v' => 'asc'],    // Should be allowed with wildcard
            ['c' => 'email', 'v' => 'INVALID'],   // Should default to asc
            ['c' => 'age'],                       // Should default to asc
            'invalid_entry',                      // Should be ignored
            ['v' => 'desc'],                      // Should be ignored - no column
            ['c' => 'name_with_injection; DROP TABLE users;', 'v' => 'desc'], // Should be sanitized
        ];

        // Should call orderBy 5 times: name, password, email, age, sanitized_name
        $this->mockBuilder->expects($this->exactly(5))
            ->method('orderBy')
            ->willReturn($this->mockBuilder);

        $this->ordering->load($orders, $mockModel);
        $result = $this->ordering->apply($this->mockBuilder);
        
        $this->assertSame($this->mockBuilder, $result);
    }

    public function testOrderableInterfaceWithSpecificColumns(): void
    {
        $orderableModel = $this->createMock(TestOrderableModel::class);
        $orderableModel->method('orders')->willReturn([
            'name' => ['allowedDirections' => ['asc', 'desc']],
            'created_at' => ['allowedDirections' => ['desc']], // Only desc allowed
            'price' => ['allowedDirections' => ['asc']], // Only asc allowed
        ]);

        $orders = [
            ['c' => 'name', 'v' => 'desc'],
            ['c' => 'created_at', 'v' => 'asc'], // Should be changed to desc
            ['c' => 'price', 'v' => 'desc'],     // Should be changed to asc
            ['c' => 'forbidden', 'v' => 'asc'],  // Should be ignored - not in orders()
        ];

        $this->mockBuilder->expects($this->exactly(3))
            ->method('orderBy')
            ->willReturn($this->mockBuilder);

        $this->ordering->load($orders, $orderableModel);
        $result = $this->ordering->apply($this->mockBuilder);

        $this->assertSame($this->mockBuilder, $result);
    }

    public function testOrderableInterfaceWithWildcard(): void
    {
        $orderableModel = $this->createMock(TestOrderableModel::class);
        $orderableModel->method('orders')->willReturn([
            '*' => ['allowedDirections' => ['asc']], // Only asc allowed globally
        ]);

        $orders = [
            ['c' => 'any_column', 'v' => 'desc'], // Should be changed to asc
            ['c' => 'another_column', 'v' => 'asc'], // Should remain asc
        ];

        $this->mockBuilder->expects($this->exactly(2))
            ->method('orderBy')
            ->willReturn($this->mockBuilder);

        $this->ordering->load($orders, $orderableModel);
        $result = $this->ordering->apply($this->mockBuilder);

        $this->assertSame($this->mockBuilder, $result);
    }

    public function testFallbackToFillableWhenNotOrderable(): void
    {
        // Test that regular models still work by falling back to fillable
        $regularModel = $this->createMock(Model::class);
        $regularModel->method('getFillable')->willReturn(['name', 'email']);

        $orders = [
            ['c' => 'name', 'v' => 'desc'],
            ['c' => 'password', 'v' => 'asc'], // Should be ignored - not fillable
        ];

        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('name', 'desc')
            ->willReturn($this->mockBuilder);

        $this->ordering->load($orders, $regularModel);
        $result = $this->ordering->apply($this->mockBuilder);

        $this->assertSame($this->mockBuilder, $result);
    }

    public function testOrderableInterfaceWithAliases(): void
    {
        $orderableModel = $this->createMock(TestOrderableModel::class);
        $orderableModel->method('orders')->willReturn([
            'user_name' => ['allowedDirections' => ['asc', 'desc'], 'column' => 'full_name'],
            'user_email' => ['allowedDirections' => ['asc'], 'column' => 'email_address'],
            'status' => ['allowedDirections' => ['asc', 'desc']], // No alias - uses original column
        ]);

        $orders = [
            ['c' => 'user_name', 'v' => 'desc'],     // Should use 'full_name' column
            ['c' => 'user_email', 'v' => 'asc'],     // Should use 'email_address' column
            ['c' => 'status', 'v' => 'asc'],         // Should use 'status' column (no alias)
        ];

        $this->mockBuilder->expects($this->exactly(3))
            ->method('orderBy')
            ->willReturn($this->mockBuilder);

        $this->ordering->load($orders, $orderableModel);
        $result = $this->ordering->apply($this->mockBuilder);

        $this->assertSame($this->mockBuilder, $result);
    }

    public function testOrderableInterfaceIgnoresUnknownAliases(): void
    {
        $orderableModel = $this->createMock(TestOrderableModel::class);
        $orderableModel->method('orders')->willReturn([
            'user_name' => ['allowedDirections' => ['asc', 'desc'], 'column' => 'full_name'],
        ]);

        $orders = [
            ['c' => 'user_name', 'v' => 'desc'],     // Valid alias
            ['c' => 'unknown_alias', 'v' => 'asc'],  // Unknown alias - should be ignored
        ];

        // Only the valid alias should result in an orderBy call
        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('full_name', 'desc')
            ->willReturn($this->mockBuilder);

        $this->ordering->load($orders, $orderableModel);
        $result = $this->ordering->apply($this->mockBuilder);

        $this->assertSame($this->mockBuilder, $result);
    }

    public function testOrderableInterfaceAliasWithDirectionRestriction(): void
    {
        $orderableModel = $this->createMock(TestOrderableModel::class);
        $orderableModel->method('orders')->willReturn([
            'creation_date' => ['allowedDirections' => ['desc'], 'column' => 'created_at'],
        ]);

        $orders = [
            ['c' => 'creation_date', 'v' => 'asc'], // Should be changed to 'desc' and use 'created_at' column
        ];

        $this->mockBuilder->expects($this->once())
            ->method('orderBy')
            ->with('created_at', 'desc') // Should use real column name and forced direction
            ->willReturn($this->mockBuilder);

        $this->ordering->load($orders, $orderableModel);
        $result = $this->ordering->apply($this->mockBuilder);

        $this->assertSame($this->mockBuilder, $result);
    }
}

// Mock class that extends Builder to provide orderBy method for testing
class MockableBuilder extends Builder
{
    public function __construct()
    {
        // Don't call parent constructor to avoid Laravel dependencies
    }
    
    public function orderBy($column, $direction = 'asc')
    {
        return $this;
    }
}

// Mock class that implements Orderable for testing
class TestOrderableModel extends Model implements Orderable
{
    public function orders(): array
    {
        return [];
    }
}