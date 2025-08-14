<?php

namespace Kdabrow\Filters\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Kdabrow\Filters\Filter;
use Kdabrow\Filters\Filterable;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    private Filter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new Filter();
    }

    public function testLoadMethodReturnsFilterInstance(): void
    {
        $input = [['c' => 'name', 'o' => '=', 'v' => 'test']];
        $model = $this->createMock(Model::class);
        
        $result = $this->filter->load($input, $model);
        
        $this->assertInstanceOf(Filter::class, $result);
        $this->assertSame($this->filter, $result);
    }

    public function testApplyWithEmptyFiltersDoesNothing(): void
    {
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $builder->expects($this->never())
            ->method('where');

        $this->filter->load([], $model);
        $this->filter->apply($builder);
    }

    public function testBasicWhereOperators(): void
    {
        $operators = ['=', '!=', '>', '<', '>=', '<='];
        
        foreach ($operators as $operator) {
            $input = [['c' => 'age', 'o' => $operator, 'v' => '25']];
            $model = $this->createMock(Model::class);
            $builder = $this->createMock(Builder::class);
            
            $model->expects($this->once())
                ->method('getFillable')
                ->willReturn(['age']);

            $builder->expects($this->once())
                ->method('where')
                ->with('age', $operator, '25');

            $filter = new Filter();
            $filter->load($input, $model);
            $filter->apply($builder);
        }
    }

    public function testLikeOperator(): void
    {
        $input = [['c' => 'name', 'o' => 'like', 'v' => 'John']];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['name']);

        $builder->expects($this->once())
            ->method('where')
            ->with('name', 'LIKE', '%John%');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testOrConditions(): void
    {
        $input = [
            ['c' => 'status', 'o' => '=', 'v' => 'active'],
            ['c' => 'status', 'o' => '=', 'v' => 'pending', 't' => 'or'],
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['status']);

        $builder->expects($this->once())
            ->method('where')
            ->with('status', '=', 'active');
            
        $builder->expects($this->once())
            ->method('orWhere')
            ->with('status', '=', 'pending');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testSanitizesInputValues(): void
    {
        $input = [['c' => 'name', 'o' => '=', 'v' => '<script>alert("xss")</script>test  ']];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['name']);

        $builder->expects($this->once())
            ->method('where')
            ->with('name', '=', 'alert(xss)test');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testBasicNestedGroup(): void
    {
        // Test: (name='John' AND age >= 18)
        $input = [
            [
                ['c' => 'name', 'o' => '=', 'v' => 'John'],
                ['c' => 'age', 'o' => '>=', 'v' => '18'],
            ]
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['name', 'age']);

        $builder->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($callback) {
                return is_callable($callback);
            }));

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testNestedGroupWithOr(): void
    {
        // Test: (name='John' OR name='Jane')
        $input = [
            [
                ['c' => 'name', 'o' => '=', 'v' => 'John'],
                ['c' => 'name', 'o' => '=', 'v' => 'Jane', 't' => 'or'],
            ]
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['name']);

        $builder->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($callback) {
                return is_callable($callback);
            }));

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testMultipleGroupsWithOrConnection(): void
    {
        // Test: (status='active') OR (role='admin')
        $input = [
            [
                ['c' => 'status', 'o' => '=', 'v' => 'active']
            ],
            [
                ['c' => 'role', 'o' => '=', 'v' => 'admin'],
                't' => 'or'
            ]
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['status', 'role']);

        $builder->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($callback) {
                return is_callable($callback);
            }));
            
        $builder->expects($this->once())
            ->method('orWhere')
            ->with($this->callback(function ($callback) {
                return is_callable($callback);
            }));

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testComplexNestedScenario(): void
    {
        // Test: (status=0 AND (name='test' OR name='test 2')) OR (status=1 AND name='test 3' AND surname='test 4')
        $input = [
            [
                ['c' => 'status', 'o' => '=', 'v' => '0'],
                ['c' => 'name', 'o' => '=', 'v' => 'test'],
                ['c' => 'name', 'o' => '=', 'v' => 'test 2', 't' => 'or'],
            ],
            [
                ['c' => 'status', 'o' => '=', 'v' => '1'],
                ['c' => 'name', 'o' => '=', 'v' => 'test 3'],
                ['c' => 'surname', 'o' => '=', 'v' => 'test 4'],
                't' => 'or'
            ]
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['status', 'name', 'surname']);

        $builder->expects($this->once())
            ->method('where')
            ->with($this->callback(function ($callback) {
                return is_callable($callback);
            }));
            
        $builder->expects($this->once())
            ->method('orWhere')
            ->with($this->callback(function ($callback) {
                return is_callable($callback);
            }));

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testMixedGroupedAndUngroupedFilters(): void
    {
        // Test: name='John' AND (status='active' OR status='pending')
        $input = [
            ['c' => 'name', 'o' => '=', 'v' => 'John'],
            [
                ['c' => 'status', 'o' => '=', 'v' => 'active'],
                ['c' => 'status', 'o' => '=', 'v' => 'pending', 't' => 'or'],
            ]
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['name', 'status']);

        $builder->expects($this->exactly(2))
            ->method('where');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testFilterableModelUsesFiltersMethod(): void
    {
        $mockFilterableModel = new class extends Model implements Filterable {
            public function filters(): array
            {
                return [
                    'name' => ['allowedOperators' => ['=', 'like']],
                    'status' => ['allowedOperators' => ['=', 'in']],
                ];
            }
        };

        $input = [
            ['c' => 'name', 'o' => '=', 'v' => 'John'],
            ['c' => 'email', 'o' => '=', 'v' => 'test@example.com'], // Should be ignored
        ];
        
        $builder = $this->createMock(Builder::class);

        $builder->expects($this->once())
            ->method('where')
            ->with('name', '=', 'John');

        $this->filter->load($input, $mockFilterableModel);
        $this->filter->apply($builder);
    }

    public function testCustomFilterMethodsAreCalled(): void
    {
        $customFilter = new class extends Filter {
            public bool $filterNameCalled = false;
            public bool $filterStatusCalled = false;

            protected function filterName($builder): void
            {
                $this->filterNameCalled = true;
                $builder->where('name', 'like', '%test%');
            }

            protected function filterStatus($builder): void
            {
                $this->filterStatusCalled = true;
                $builder->where('status', '=', 'active');
            }
        };

        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $builder->expects($this->exactly(2))
            ->method('where');

        $customFilter->load([], $model);
        $customFilter->apply($builder);

        $this->assertTrue($customFilter->filterNameCalled);
        $this->assertTrue($customFilter->filterStatusCalled);
    }

    public function testInvalidFiltersAreSkipped(): void
    {
        $input = [
            ['c' => 'invalid_column', 'o' => '=', 'v' => 'test'], // Invalid column
            ['c' => 'name', 'o' => 'invalid_op', 'v' => 'test'], // Invalid operator
            ['o' => '=', 'v' => 'test'], // Missing column
            ['c' => 'name', 'o' => '=', 'v' => 'John'], // Valid
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['name', 'status']);

        $builder->expects($this->once()) // Only valid filter should be applied
            ->method('where')
            ->with('name', '=', 'John');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testSecuritySqlInjectionPrevention(): void
    {
        $maliciousInputs = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "test\x00\x0A\x0D\x1A\x22\x27\x5C",
        ];

        foreach ($maliciousInputs as $maliciousInput) {
            $input = [['c' => 'name', 'o' => '=', 'v' => $maliciousInput]];
            $model = $this->createMock(Model::class);
            $builder = $this->createMock(Builder::class);
            
            $model->expects($this->once())
                ->method('getFillable')
                ->willReturn(['name']);

            $builder->expects($this->once())
                ->method('where')
                ->with($this->equalTo('name'), $this->equalTo('='), $this->callback(function ($value) {
                    $this->assertStringNotContainsString("'", $value);
                    $this->assertStringNotContainsString('"', $value);
                    $this->assertStringNotContainsString('\\', $value);
                    $this->assertStringNotContainsString("\x00", $value);
                    return true;
                }));

            $filter = new Filter();
            $filter->load($input, $model);
            $filter->apply($builder);
        }
    }

    public function testParseArrayValueMethod(): void
    {
        $filter = new class extends Filter {
            public function testParseArrayValue(string $value): array
            {
                return $this->parseArrayValue($value);
            }
        };

        $this->assertEquals(['active', 'pending', 'suspended'], $filter->testParseArrayValue('active,pending,suspended'));
        $this->assertEquals(['test'], $filter->testParseArrayValue('test'));
        $this->assertEquals([], $filter->testParseArrayValue(''));
        $this->assertEquals(['a', 'b', 'c'], $filter->testParseArrayValue(' a , b , c '));
    }

    public function testSanitizeValueMethod(): void
    {
        $filter = new class extends Filter {
            public function testSanitizeValue(string $value): string
            {
                return $this->sanitizeValue($value);
            }
        };

        $this->assertEquals('test', $filter->testSanitizeValue('<script>test</script>'));
        $this->assertEquals('hello world', $filter->testSanitizeValue('  hello world  '));
        $this->assertEquals('test', $filter->testSanitizeValue("test\x00\x0A\x0D\x1A\x22\x27\x5C"));
        $this->assertEquals('safe content', $filter->testSanitizeValue('<b>safe</b> <i>content</i>'));
    }

    public function testEmptyNestedGroupsAreIgnored(): void
    {
        $input = [
            ['c' => 'name', 'o' => '=', 'v' => 'John'],
            [], // Empty group
            [
                ['c' => 'status', 'o' => '=', 'v' => 'active']
            ]
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['name', 'status']);

        $builder->expects($this->exactly(2))
            ->method('where');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testNullOperatorsDoNotRequireValue(): void
    {
        $input = [
            ['c' => 'deleted_at', 'o' => 'null'],     // No 'v' parameter - should work
            ['c' => 'verified_at', 'o' => 'nnull'],  // No 'v' parameter - should work
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(QueryBuilder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['deleted_at', 'verified_at']);

        $builder->expects($this->once())
            ->method('whereNull')
            ->with('deleted_at');
            
        $builder->expects($this->once())
            ->method('whereNotNull')
            ->with('verified_at');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testNullOperatorsIgnoreProvidedValue(): void
    {
        $input = [
            ['c' => 'deleted_at', 'o' => 'null', 'v' => 'ignored'],    // Value should be ignored
            ['c' => 'verified_at', 'o' => 'nnull', 'v' => 'ignored'], // Value should be ignored
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(QueryBuilder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['deleted_at', 'verified_at']);

        $builder->expects($this->once())
            ->method('whereNull')
            ->with('deleted_at');
            
        $builder->expects($this->once())
            ->method('whereNotNull')
            ->with('verified_at');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testOtherOperatorsStillRequireValue(): void
    {
        $input = [
            ['c' => 'name', 'o' => '='],              // Missing 'v' - should be ignored
            ['c' => 'age', 'o' => '>', 'v' => '18'],  // Has 'v' - should work
        ];
        $model = $this->createMock(Model::class);
        $builder = $this->createMock(Builder::class);
        
        $model->expects($this->once())
            ->method('getFillable')
            ->willReturn(['name', 'age']);

        $builder->expects($this->once()) // Only the valid filter should be applied
            ->method('where')
            ->with('age', '>', '18');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testFilterableInterfaceWithAliases(): void
    {
        $model = $this->createMock(FilterableTestModel::class);
        $model->method('filters')->willReturn([
            'user_name' => ['allowedOperators' => ['=', 'like'], 'column' => 'full_name'],
            'user_email' => ['allowedOperators' => ['='], 'column' => 'email_address'],
            'status' => ['allowedOperators' => ['=', 'in']], // No alias - uses original column
        ]);

        $builder = $this->createMock(Builder::class);

        $input = [
            ['c' => 'user_name', 'o' => 'like', 'v' => 'John'],      // Should use 'full_name' column
            ['c' => 'user_email', 'o' => '=', 'v' => 'test@test.com'], // Should use 'email_address' column
            ['c' => 'status', 'o' => '=', 'v' => 'active'],            // Should use 'status' column (no alias)
        ];

        $builder->expects($this->exactly(3))
            ->method('where');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testFilterableInterfaceAliasWithNestedConditions(): void
    {
        $model = $this->createMock(FilterableTestModel::class);
        $model->method('filters')->willReturn([
            'user_name' => ['allowedOperators' => ['=', 'like'], 'column' => 'full_name'],
            'user_email' => ['allowedOperators' => ['='], 'column' => 'email_address'],
        ]);

        $builder = $this->createMock(Builder::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $builder->method('getQuery')->willReturn($queryBuilder);

        $input = [
            [
                ['c' => 'user_name', 'o' => 'like', 'v' => 'John'],  // Should use 'full_name'
                ['c' => 'user_email', 'o' => '=', 'v' => 'test@test.com', 't' => 'or'], // Should use 'email_address'
            ]
        ];

        // Expect the nested group to be wrapped in where clause
        $builder->expects($this->once())
            ->method('where');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }

    public function testFilterableInterfaceIgnoresUnknownAliases(): void
    {
        $model = $this->createMock(FilterableTestModel::class);
        $model->method('filters')->willReturn([
            'user_name' => ['allowedOperators' => ['=', 'like'], 'column' => 'full_name'],
        ]);

        $builder = $this->createMock(Builder::class);

        $input = [
            ['c' => 'user_name', 'o' => 'like', 'v' => 'John'],     // Valid alias
            ['c' => 'unknown_alias', 'o' => '=', 'v' => 'test'],    // Unknown alias - should be ignored
        ];

        // Only the valid alias should result in a where clause
        $builder->expects($this->once())
            ->method('where')
            ->with('full_name', 'LIKE', '%John%');

        $this->filter->load($input, $model);
        $this->filter->apply($builder);
    }
}

// Mock class that implements Filterable for alias testing
class FilterableTestModel extends \Illuminate\Database\Eloquent\Model implements \Kdabrow\Filters\Filterable
{
    public function filters(): array
    {
        return [];
    }
}