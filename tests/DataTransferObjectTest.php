<?php

declare(strict_types=1);

namespace Spatie\DataTransferObject\Tests;

use ArrayIterator;
use Spatie\DataTransferObject\DataTransferObject;
use Spatie\DataTransferObject\DataTransferObjectError;
use Spatie\DataTransferObject\Tests\TestClasses\DummyClass;
use Spatie\DataTransferObject\Tests\TestClasses\EmptyChild;
use Spatie\DataTransferObject\Tests\TestClasses\NestedChild;
use Spatie\DataTransferObject\Tests\TestClasses\NestedParent;
use Spatie\DataTransferObject\Tests\TestClasses\NestedParentOfMany;
use Spatie\DataTransferObject\Tests\TestClasses\OtherClass;
use Spatie\DataTransferObject\Tests\TestClasses\TestDataTransferObject;

class DataTransferObjectTest extends TestCase
{
    /** @test */
    public function only_the_type_hinted_type_may_be_passed()
    {
        new class(['foo' => 'value']) extends DataTransferObject {
            /** @var string */
            public $foo;
        };

        $this->markTestSucceeded();

        $this->expectException(DataTransferObjectError::class);
        $this->expectExceptionMessageRegExp('/Invalid type: expected `class@anonymous[^:]+::foo` to be of type `string`, instead got value ``, which is boolean/');

        new class(['foo' => false]) extends DataTransferObject {
            /** @var string */
            public $foo;
        };
    }

    /** @test */
    public function invalid_types_set_exception_properties()
    {
        try {
            new class(['foo' => 'value']) extends DataTransferObject {
                /** @var boolean */
                public $foo;
            };
        } catch (DataTransferObjectError $error) {
            $this->assertEquals('invalidType', $error->getError());
            $this->assertRegExp('/class@anonymous/', $error->getClass());
            $this->assertEquals('foo', $error->getProperty());
            $this->assertEquals('value', $error->getValue());
            $this->assertEquals('string', $error->getType());
            $this->assertEquals(['boolean'], $error->getExpectedTypes());
        }
    }

    /** @test */
    public function union_types_are_supported()
    {
        new class(['foo' => 'value']) extends DataTransferObject {
            /** @var string|bool */
            public $foo;
        };

        new class(['foo' => false]) extends DataTransferObject {
            /** @var string|bool */
            public $foo;
        };

        $this->markTestSucceeded();
    }

    /** @test */
    public function nullable_types_are_supported()
    {
        new class(['foo' => null]) extends DataTransferObject {
            /** @var string|null */
            public $foo;
        };

        $this->markTestSucceeded();
    }

    /** @test */
    public function default_values_are_supported()
    {
        $valueObject = new class(['bar' => true]) extends DataTransferObject {
            /** @var string */
            public $foo = 'abc';

            /** @var bool */
            public $bar;
        };

        $this->assertEquals(['foo' => 'abc', 'bar' => true], $valueObject->all());
    }

    /** @test */
    public function null_is_allowed_only_if_explicitly_specified()
    {
        $this->expectException(DataTransferObjectError::class);
        $this->expectExceptionMessageRegExp('/Non-nullable property `class@anonymous[^:]+::foo` has not been initialized./');

        new class(['foo' => null]) extends DataTransferObject {
            /** @var string */
            public $foo;
        };
    }

    /** @test */
    public function unknown_properties_throw_an_error()
    {
        $this->expectException(DataTransferObjectError::class);
        $this->expectExceptionMessageRegExp('/Public properties `bar` not found on class@anonymous/');

        new class(['bar' => null]) extends DataTransferObject {
        };
    }

    /** @test */
    public function unknown_properties_show_a_comprehensive_error_message()
    {
        try {
            new class(['foo' => null, 'bar' => null]) extends DataTransferObject {
            };
        } catch (DataTransferObjectError $error) {
            $this->assertContains('`foo`', $error->getMessage());
            $this->assertContains('`bar`', $error->getMessage());
        }
    }

    /** @test */
    public function unknown_properties_set_exception_properties()
    {
        try {
            new class(['foo' => null, 'bar' => null]) extends DataTransferObject {
            };
        } catch (DataTransferObjectError $error) {
            $this->assertEquals('unknownProperties', $error->getError());
            $this->assertRegExp('/class@anonymous/', $error->getClass());
            $this->assertEquals(['foo', 'bar'], $error->getProperties());
        }
    }

    /** @test */
    public function only_returns_filtered_properties()
    {
        $valueObject = new class(['foo' => 1, 'bar' => 2]) extends DataTransferObject {
            /** @var int */
            public $foo;

            /** @var int */
            public $bar;
        };

        $this->assertEquals(['foo' => 1], $valueObject->only('foo')->toArray());
    }

    /** @test */
    public function except_returns_filtered_properties()
    {
        $valueObject = new class(['foo' => 1, 'bar' => 2]) extends DataTransferObject {
            /** @var int */
            public $foo;

            /** @var int */
            public $bar;
        };

        $this->assertEquals(['foo' => 1], $valueObject->except('bar')->toArray());
    }

    /** @test */
    public function all_returns_all_properties()
    {
        $valueObject = new class(['foo' => 1, 'bar' => 2]) extends DataTransferObject {
            /** @var int */
            public $foo;

            /** @var int */
            public $bar;
        };

        $this->assertEquals(['foo' => 1, 'bar' => 2], $valueObject->all());
    }

    /** @test */
    public function mixed_is_supported()
    {
        new class(['foo' => 'abc']) extends DataTransferObject {
            /** @var mixed */
            public $foo;
        };

        new class(['foo' => 1]) extends DataTransferObject {
            /** @var mixed */
            public $foo;
        };

        $this->markTestSucceeded();
    }

    /** @test */
    public function float_is_supported()
    {
        new class(['foo' => 5.1]) extends DataTransferObject {
            /** @var float */
            public $foo;
        };

        $this->markTestSucceeded();
    }

    /** @test */
    public function classes_are_supported()
    {
        new class(['foo' => new DummyClass()]) extends DataTransferObject {
            /** @var \Spatie\DataTransferObject\Tests\TestClasses\DummyClass */
            public $foo;
        };

        $this->markTestSucceeded();

        $this->expectException(DataTransferObjectError::class);
        $this->expectExceptionMessageRegExp('/Invalid type: expected `class@anonymous[^:]+::foo` to be of type `\\\Spatie\\\DataTransferObject\\\Tests\\\TestClasses\\\DummyClass`, instead got value `class@anonymous[^`]+`, which is object/');

        new class(['foo' => new class() {
        },
        ]) extends DataTransferObject
        {
            /** @var \Spatie\DataTransferObject\Tests\TestClasses\DummyClass */
            public $foo;
        };
    }

    /** @test */
    public function generic_collections_are_supported()
    {
        new class(['foo' => [new DummyClass()]]) extends DataTransferObject {
            /** @var \Spatie\DataTransferObject\Tests\TestClasses\DummyClass[] */
            public $foo;
        };

        $this->markTestSucceeded();

        $this->expectException(DataTransferObjectError::class);
        $this->expectExceptionMessageRegExp('/Invalid type: expected `class@anonymous[^:]+::foo` to be of type `\\\Spatie\\\DataTransferObject\\\Tests\\\TestClasses\\\DummyClass\[\]`, instead got value `array`, which is array/');

        new class(['foo' => [new OtherClass()]]) extends DataTransferObject {
            /** @var \Spatie\DataTransferObject\Tests\TestClasses\DummyClass[] */
            public $foo;
        };
    }

    /** @test */
    public function an_exception_is_thrown_for_a_generic_collection_of_null()
    {
        $this->expectException(DataTransferObjectError::class);
        $this->expectExceptionMessageRegExp('/Invalid type: expected `class@anonymous[^:]+::foo` to be of type `string\[\]`, instead got value `array`, which is array./');

        new class(['foo' => [null]]) extends DataTransferObject {
            /** @var string[] */
            public $foo;
        };
    }

    /** @test */
    public function an_exception_is_thrown_when_property_was_not_initialised()
    {
        $this->expectException(DataTransferObjectError::class);
        $this->expectExceptionMessageRegExp('/Non-nullable property `class@anonymous[^:]+::foo` has not been initialized./');

        new class([]) extends DataTransferObject {
            /** @var string */
            public $foo;
        };
    }

    /** @test */
    public function uninitialized_properties_set_exception_properties()
    {
        try {
            new class([]) extends DataTransferObject {
                /** @var string */
                public $foo;
            };
        } catch (DataTransferObjectError $error) {
            // https://github.com/spatie/data-transfer-object/pull/95
            // PR #95 fixes a bug that causes the "invalidType" error to be thrown for
            // uninitialized non-nullable properties instead of the "uninitialized" error.
            // As of this change the PR is still open, so this test checks for both errors
            // to handle both pre-merge and post-merge situations.
            $this->assertContains($error->getError(), ['uninitialized', 'invalidType']);
            $this->assertRegExp('/class@anonymous/', $error->getClass());
            $this->assertEquals('foo', $error->getProperty());
        }
    }

    /** @test */
    public function empty_type_declaration_allows_everything()
    {
        new class(['foo' => new DummyClass()]) extends DataTransferObject {
            public $foo;
        };

        new class(['foo' => null]) extends DataTransferObject {
            public $foo;
        };

        new class(['foo' => null]) extends DataTransferObject {
            /** This is a variable without type declaration */
            public $foo;
        };

        new class(['foo' => 1]) extends DataTransferObject {
            public $foo;
        };

        $this->markTestSucceeded();
    }

    /** @test */
    public function nested_dtos_are_automatically_cast_from_arrays_to_objects()
    {
        $data = [
            'name' => 'parent',
            'child' => [
                'name' => 'child',
            ],
        ];

        $object = new NestedParent($data);

        $this->assertInstanceOf(NestedChild::class, $object->child);
        $this->assertEquals('parent', $object->name);
        $this->assertEquals('child', $object->child->name);
    }

    /** @test */
    public function nested_dtos_are_recursive_cast_from_object_to_array_when_to_array()
    {
        $data = [
            'name' => 'parent',
            'child' => [
                'name' => 'child',
            ],
        ];

        $object = new NestedParent($data);

        $this->assertEquals(['name' => 'child'], $object->toArray()['child']);

        $valueObject = new class(['childs' => [new NestedChild(['name' => 'child'])]]) extends DataTransferObject {
            /** @var Spatie\DataTransferObject\Tests\TestClasses\NestedChild[] */
            public $childs;
        };

        $this->assertEquals(['name' => 'child'], $valueObject->toArray()['childs'][0]);
    }

    /** @test */
    public function nested_array_dtos_are_automatically_cast_to_arrays_of_dtos()
    {
        $data = [
            'name' => 'parent',
            'children' => [
                ['name' => 'child'],
            ],
        ];

        $object = new NestedParentOfMany($data);

        $this->assertNotEmpty($object->children);
        $this->assertInstanceOf(NestedChild::class, $object->children[0]);
        $this->assertEquals('parent', $object->name);
        $this->assertEquals('child', $object->children[0]->name);
    }

    /** @test */
    public function nested_array_dtos_are_recursive_cast_to_arrays_of_dtos()
    {
        $data = [
            'children' => [
                [
                    'name' => 'child',
                    'children' => [
                        ['name' => 'grandchild'],
                    ],
                ],
            ],
        ];

        $object = new class($data) extends DataTransferObject {
            /** @var \Spatie\DataTransferObject\Tests\TestClasses\NestedParentOfMany[] */
            public $children;
        };

        $this->assertEquals(['name' => 'grandchild'], $object->toArray()['children'][0]['children'][0]);
    }

    /** @test */
    public function nested_array_dtos_cannot_cast_with_null()
    {
        $this->expectException(DataTransferObjectError::class);
        $this->expectExceptionMessage('Non-nullable property `Spatie\DataTransferObject\Tests\TestClasses\NestedParentOfMany::children` has not been initialized.');

        new NestedParentOfMany([
            'name' => 'parent',
        ]);
    }

    /** @test */
    public function nested_array_dtos_can_be_nullable()
    {
        $object = new class(['children' => null]) extends DataTransferObject {
            /** @var Spatie\DataTransferObject\Tests\TestClasses\NestedChild[]|null */
            public $children;
        };

        $this->assertNull($object->children);
    }

    /** @test */
    public function empty_dto_objects_can_be_cast_using_arrays()
    {
        $object = new class(['child' => []]) extends DataTransferObject {
            /** @var \Spatie\DataTransferObject\Tests\TestClasses\EmptyChild */
            public $child;
        };

        $this->assertInstanceOf(EmptyChild::class, $object->child);
    }

    /** @test */
    public function empty_constructor_is_supported()
    {
        $valueObject = new class() extends DataTransferObject {
            /** @var string */
            public $foo = 'abc';

            /** @var bool|null */
            public $bar;
        };

        $this->assertEquals(['foo' => 'abc', 'bar' => null], $valueObject->all());
    }

    /** @test */
    public function iterable_is_supported()
    {
        new class(['strings' => ['foo', 'bar']]) extends DataTransferObject {
            /** @var iterable<string> */
            public $strings;
        };

        new class(['strings' => new ArrayIterator(['foo', 'bar'])]) extends DataTransferObject {
            /** @var iterable<string> */
            public $strings;
        };

        new class(['mixeds' => ['foo', 1]]) extends DataTransferObject {
            /** @var iterable */
            public $mixeds;
        };

        new class(['mixeds' => new ArrayIterator(['foo', 1])]) extends DataTransferObject {
            /** @var iterable */
            public $mixeds;
        };

        $this->markTestSucceeded();
    }

    /** @test */
    public function an_exception_is_thrown_for_incoherent_iterator_type()
    {
        $this->expectException(DataTransferObjectError::class);
        $this->expectExceptionMessageRegExp('/Invalid type: expected `class@anonymous[^:]+::strings` to be of type `iterable<string>`, instead got value `array`, which is array./');

        new class(['strings' => ['foo', 1]]) extends DataTransferObject {
            /** @var iterable<string> */
            public $strings;
        };
    }

    /** @test */
    public function nested_dtos_are_automatically_cast_from_arrays_to_objects_with_iterable_syntax()
    {
        $data = [
            'children' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
        ];

        $object = new class($data) extends DataTransferObject {
            /** @var iterable<\Spatie\DataTransferObject\Tests\TestClasses\NestedChild>|iterable<string> */
            public $children;
        };

        $this->assertTrue(is_array($object->children));
        $this->assertCount(2, $object->children);
        $this->assertContainsOnly(NestedChild::class, $object->children);
        $this->assertEquals('Alice', $object->children[0]->name);
        $this->assertEquals('Bob', $object->children[1]->name);
    }

    /** @test */
    public function array_of_dtos()
    {
        $data = [
            [
                'testProperty' => 1,
            ],
            [
                'testProperty' => 2,
            ],
        ];

        $arrayOf = TestDataTransferObject::arrayOf($data);

        $this->assertCount(2, $arrayOf);
        $this->assertSame(1, $arrayOf[0]->testProperty);
        $this->assertSame(2, $arrayOf[1]->testProperty);
    }


    /** @test */
    public function ignore_static_public_properties()
    {
        $object = new class(['foo' => 'bar']) extends DataTransferObject {
            /** @var string */
            public $foo;
            public static $prop;
        };

        $this->assertSame('bar', $object->foo);
    }

    /** @test */
    public function ignore_static_public_properties_when_serializing()
    {
        $object = new class(['foo' => 'bar']) extends DataTransferObject {
            /** @var string */
            public $foo;
            public static $prop;
        };

        $expected = [
            'foo' => 'bar',
        ];

        $this->assertSame($expected, $object->all());
    }
}
