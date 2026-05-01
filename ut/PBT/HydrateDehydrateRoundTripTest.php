<?php
// Feature: release-2.0, Property 1: Hydrate/Dehydrate Round-Trip
// Validates: Requirements 11.2

namespace Oasis\Mlib\ODM\Dynamodb\Ut\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;
use PHPUnit\Framework\TestCase;

/**
 * Dynamically-defined model classes for PBT.
 *
 * We need model classes whose fields cover all DynamoDB data types.
 * These are defined once at file scope so Eris iterations reuse them.
 */

#[Item(
    table: 'pbt_all_types',
    primaryIndex: new Index(hash: 'pk'),
)]
class AllTypesModel
{
    #[Field(type: 'string', name: 'pk')]
    protected ?string $pk = null;

    #[Field(type: 'string')]
    protected ?string $stringField = null;

    #[Field(type: 'number')]
    protected mixed $numberField = null;

    #[Field(type: 'binary')]
    protected mixed $binaryField = null;

    #[Field(type: 'bool')]
    protected mixed $boolField = null;

    #[Field(type: 'null')]
    protected mixed $nullField = null;

    #[Field(type: 'list')]
    protected mixed $listField = null;

    #[Field(type: 'map')]
    protected mixed $mapField = null;

    #[Field(type: 'string')]
    protected mixed $stringSetField = null;

    #[Field(type: 'number')]
    protected mixed $numberSetField = null;

    #[Field(type: 'binary')]
    protected mixed $binarySetField = null;
}

class HydrateDehydrateRoundTripTest extends TestCase
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
     * Property 1: Hydrate/Dehydrate Round-Trip
     *
     * FOR ALL valid field data arrays covering DynamoDB types (S/N/B/BOOL/NULL/L/M/SS/NS/BS),
     * dehydrate(hydrate(data)) SHALL produce an equivalent array,
     * accounting for DynamoDB null/empty-string equivalence.
     */
    public function testHydrateDehydrateRoundTrip(): void
    {
        $this
            ->limitTo(200)
            ->forAll(
                $this->dynamoDbDataGenerator()
            )
            ->then(function (array $data): void {
                $obj        = $this->reflection->hydrate($data);
                $dehydrated = $this->reflection->dehydrate($obj);

                foreach ($data as $key => $originalValue) {
                    $this->assertArrayHasKey($key, $dehydrated, "Missing key after round-trip: $key");
                    $roundTripped = $dehydrated[$key];
                    $this->assertValuesEquivalent(
                        $originalValue,
                        $roundTripped,
                        $key,
                    );
                }
            });
    }

    /**
     * Build a generator that produces associative arrays matching AllTypesModel fields.
     *
     * Covers all DynamoDB data types: S, N, B, BOOL, NULL, L, M, SS, NS, BS.
     */
    private function dynamoDbDataGenerator(): \Eris\Generator
    {
        return Generators::associative([
            'pk'             => $this->nonEmptyString(),       // S — primary key, must be non-empty
            'stringField'    => $this->stringOrNull(),         // S (nullable)
            'numberField'    => Generators::oneOf(             // N
                Generators::int(),
                Generators::float(),
            ),
            'binaryField'    => Generators::string(),          // B
            'boolField'      => Generators::bool(),            // BOOL
            'nullField'      => Generators::constant(null),    // NULL
            'listField'      => $this->listGenerator(),        // L
            'mapField'       => $this->mapGenerator(),         // M
            'stringSetField' => $this->stringSetGenerator(),   // SS
            'numberSetField' => $this->numberSetGenerator(),   // NS
            'binarySetField' => Generators::string(),          // BS (binary data as string)
        ]);
    }

    private function nonEmptyString(): \Eris\Generator
    {
        return Generators::suchThat(
            fn(string $s) => $s !== '',
            Generators::string(),
        );
    }

    private function stringOrNull(): \Eris\Generator
    {
        return Generators::oneOf(
            Generators::string(),
            Generators::constant(null),
        );
    }

    private function listGenerator(): \Eris\Generator
    {
        // Generate a list of simple scalar values
        return Generators::seq(
            Generators::oneOf(
                Generators::string(),
                Generators::int(),
                Generators::bool(),
                Generators::constant(null),
            )
        );
    }

    private function mapGenerator(): \Eris\Generator
    {
        // Generate an associative array with string keys and scalar values
        return Generators::associative([
            'a' => Generators::oneOf(Generators::string(), Generators::int()),
            'b' => Generators::oneOf(Generators::string(), Generators::int()),
        ]);
    }

    private function stringSetGenerator(): \Eris\Generator
    {
        // SS: set of strings — generate as a simple string value
        // (DynamoDB SS is a set, but ODM stores it as a scalar field value)
        return Generators::string();
    }

    private function numberSetGenerator(): \Eris\Generator
    {
        // NS: set of numbers — generate as a simple number value
        return Generators::int();
    }

    /**
     * Assert two values are equivalent under DynamoDB semantics.
     *
     * Key equivalence: null ≡ "" for string-type fields.
     * DynamoDB stores "" as null, so hydrate casts null→"" for string fields.
     */
    private function assertValuesEquivalent(mixed $original, mixed $roundTripped, string $key): void
    {
        // Handle null/empty-string equivalence for string-type fields
        if ($this->isNullEmptyEquivalent($original, $roundTripped)) {
            return; // equivalent under DynamoDB semantics
        }

        // Handle float comparison via string representation (matching isDataEqual logic)
        if (is_float($original) && is_float($roundTripped)) {
            $this->assertSame(
                "$original",
                "$roundTripped",
                "Float mismatch for key: $key",
            );
            return;
        }

        // Handle array comparison recursively
        if (is_array($original) && is_array($roundTripped)) {
            $this->assertSame(
                count($original),
                count($roundTripped),
                "Array count mismatch for key: $key",
            );
            foreach ($original as $k => $v) {
                $this->assertArrayHasKey($k, $roundTripped, "Missing array key $k in $key");
                $this->assertValuesEquivalent($v, $roundTripped[$k], "$key.$k");
            }
            return;
        }

        $this->assertSame(
            $original,
            $roundTripped,
            "Value mismatch for key: $key",
        );
    }

    /**
     * Check if two values are equivalent under DynamoDB null/empty-string semantics.
     */
    private function isNullEmptyEquivalent(mixed $a, mixed $b): bool
    {
        return (is_null($a) && $b === '')
            || (is_null($b) && $a === '');
    }
}
