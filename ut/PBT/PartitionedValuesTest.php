<?php
// Feature: release-2.0, Property 6: Partitioned Values Output Size
// Validates: Requirements 11.7

namespace Oasis\Mlib\ODM\Dynamodb\Ut\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;
use Oasis\Mlib\ODM\Dynamodb\Attributes\PartitionedHashKey;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;
use PHPUnit\Framework\TestCase;

class PartitionedValuesTest extends TestCase
{
    use TestTrait;

    /**
     * Property 6: Partitioned Values Output Size
     *
     * FOR ALL valid partition sizes (positive integers) and base value strings,
     * getAllPartitionedValues() SHALL return exactly `size` elements.
     */
    public function testPartitionedValuesOutputSize(): void
    {
        $this
            ->limitTo(200)
            ->forAll(
                Generators::choose(1, 256),  // partition size (positive integer, reasonable range)
                Generators::string(),        // base value
            )
            ->then(function (int $size, string $baseValue): void {
                // Dynamically create a model class with the given partition size
                // by using the existing PhkModel and directly calling getAllPartitionedValues
                // with a known partitioned hash key name.
                //
                // Since getAllPartitionedValues checks $this->partitionedHashKeys,
                // we build a reflection with a model that has a PartitionedHashKey of the given size.
                // However, the size is baked into the Attribute at parse time.
                //
                // Alternative approach: directly test the logic by constructing an ItemReflection
                // for a fixed model and verifying the output count matches the model's size,
                // then also test with a model that has a different size.
                //
                // The cleanest approach: use the PhkModel (size=16) and verify count=16,
                // then also test the User model (size=16) and Game model (size=4).
                // But that doesn't test arbitrary sizes.
                //
                // Best approach: directly invoke getAllPartitionedValues on a reflection
                // where we know the partition size, and verify the output.
                // For arbitrary sizes, we can use reflection to set the partitionedHashKeys
                // property directly.

                $reflection = new ItemReflection(PhkModel::class, []);
                $reflection->parse();

                // Use reflection to inject a PartitionedHashKey with the generated size
                $phk = new PartitionedHashKey(
                    hashField: 'hashSource',
                    baseField: 'baseValue',
                    size: $size,
                );

                $refProp = new \ReflectionProperty($reflection, 'partitionedHashKeys');
                $refProp->setValue($reflection, ['partitioned' => $phk]);

                $values = $reflection->getAllPartitionedValues('partitioned', $baseValue);

                $this->assertCount(
                    $size,
                    $values,
                    "getAllPartitionedValues should return exactly $size elements for size=$size",
                );

                // Each value should start with the base value
                foreach ($values as $v) {
                    $this->assertStringStartsWith(
                        "$baseValue-",
                        $v,
                        'Each partitioned value should start with baseValue-',
                    );
                }
            });
    }
}
