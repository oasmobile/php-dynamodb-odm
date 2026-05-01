<?php
// Feature: release-2.0, Property 4: PartitionedHashKey Computation Determinism
// Validates: Requirements 11.5

namespace Oasis\Mlib\ODM\Dynamodb\Ut\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;
use Oasis\Mlib\ODM\Dynamodb\Attributes\PartitionedHashKey;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;
use Oasis\Mlib\ODM\Dynamodb\ManagedItemState;
use PHPUnit\Framework\TestCase;

/**
 * Model with a PartitionedHashKey for determinism testing.
 */
#[Item(
    table: 'pbt_phk_model',
    primaryIndex: new Index(hash: 'pk'),
)]
class PhkModel
{
    #[Field(type: 'string', name: 'pk')]
    protected ?string $pk = null;

    #[Field(type: 'string')]
    protected mixed $baseValue = null;

    #[Field(type: 'string')]
    protected mixed $hashSource = null;

    #[Field(type: 'string')]
    #[PartitionedHashKey(hashField: 'hashSource', baseField: 'baseValue', size: 16)]
    protected mixed $partitioned = null;
}

class PartitionedHashKeyTest extends TestCase
{
    use TestTrait;

    private ItemReflection $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ItemReflection(PhkModel::class, []);
        $this->reflection->parse();
    }

    /**
     * Property 4: PartitionedHashKey Computation Determinism
     *
     * FOR ALL valid base values and hash source strings,
     * computing the partitioned hash key twice with the same inputs
     * SHALL produce the same result.
     */
    public function testPartitionedHashKeyIsDeterministic(): void
    {
        $this
            ->limitTo(200)
            ->forAll(
                Generators::string(),  // baseValue
                Generators::string(),  // hashSource
            )
            ->then(function (string $baseValue, string $hashSource): void {
                // First computation
                $obj1 = $this->reflection->hydrate([
                    'pk'         => 'test-pk',
                    'baseValue'  => $baseValue,
                    'hashSource' => $hashSource,
                ]);
                $state1 = new ManagedItemState($this->reflection, $obj1);
                $state1->updatePartitionedHashKeys();
                $result1 = $this->reflection->getPropertyValue($obj1, 'partitioned');

                // Second computation with identical inputs
                $obj2 = $this->reflection->hydrate([
                    'pk'         => 'test-pk',
                    'baseValue'  => $baseValue,
                    'hashSource' => $hashSource,
                ]);
                $state2 = new ManagedItemState($this->reflection, $obj2);
                $state2->updatePartitionedHashKeys();
                $result2 = $this->reflection->getPropertyValue($obj2, 'partitioned');

                $this->assertSame(
                    $result1,
                    $result2,
                    "PartitionedHashKey computation should be deterministic for "
                    . "baseValue='$baseValue', hashSource='$hashSource'",
                );

                // Verify the result has the expected format: "{baseValue}-{hexDigit}"
                $this->assertStringStartsWith(
                    "$baseValue-",
                    $result1,
                    'Partitioned value should start with baseValue followed by a dash',
                );
            });
    }
}
