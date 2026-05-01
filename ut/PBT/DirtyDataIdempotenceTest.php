<?php
// Feature: release-2.0, Property 2: Dirty Data Detection Idempotence
// Validates: Requirements 11.3

namespace Oasis\Mlib\ODM\Dynamodb\Ut\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;
use Oasis\Mlib\ODM\Dynamodb\ManagedItemState;
use PHPUnit\Framework\TestCase;

class DirtyDataIdempotenceTest extends TestCase
{
    use TestTrait;

    private ItemReflection $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ItemReflection(AllTypesModel::class, []);
        $this->reflection->parse();
    }

    /**
     * Property 2: Dirty Data Detection Idempotence
     *
     * FOR ALL valid field data arrays, a ManagedItemState initialized with data
     * and not modified SHALL report hasDirtyData() as false.
     */
    public function testUnmodifiedObjectIsNotDirty(): void
    {
        $this
            ->limitTo(200)
            ->forAll(
                $this->fieldDataGenerator()
            )
            ->then(function (array $data): void {
                // Hydrate an object from the data
                $obj = $this->reflection->hydrate($data);

                // Dehydrate to get the "original data" as the system would store it
                $originalData = $this->reflection->dehydrate($obj);

                // Create a managed state with the same object and its dehydrated data
                $state = new ManagedItemState($this->reflection, $obj, $originalData);

                // Without any modification, hasDirtyData() must return false
                $this->assertFalse(
                    $state->hasDirtyData(),
                    'Unmodified object should not be reported as dirty. '
                    . 'Data: ' . json_encode($data),
                );
            });
    }

    /**
     * Generate field data arrays matching AllTypesModel.
     * Reuses the same type coverage as Property 1.
     */
    private function fieldDataGenerator(): \Eris\Generator
    {
        return Generators::associative([
            'pk'             => Generators::suchThat(
                fn(string $s) => $s !== '',
                Generators::string(),
            ),
            'stringField'    => Generators::oneOf(
                Generators::string(),
                Generators::constant(null),
            ),
            'numberField'    => Generators::oneOf(
                Generators::int(),
                Generators::float(),
            ),
            'binaryField'    => Generators::string(),
            'boolField'      => Generators::bool(),
            'nullField'      => Generators::constant(null),
            'listField'      => Generators::seq(
                Generators::oneOf(
                    Generators::string(),
                    Generators::int(),
                    Generators::bool(),
                    Generators::constant(null),
                ),
            ),
            'mapField'       => Generators::associative([
                'a' => Generators::oneOf(Generators::string(), Generators::int()),
                'b' => Generators::oneOf(Generators::string(), Generators::int()),
            ]),
            'stringSetField' => Generators::string(),
            'numberSetField' => Generators::int(),
            'binarySetField' => Generators::string(),
        ]);
    }
}
