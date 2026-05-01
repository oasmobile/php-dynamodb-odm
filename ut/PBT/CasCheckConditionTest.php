<?php
// Feature: release-2.0, Property 3: CAS Check Condition Invariant
// Validates: Requirements 11.4

namespace Oasis\Mlib\ODM\Dynamodb\Ut\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;
use Oasis\Mlib\ODM\Dynamodb\ManagedItemState;
use PHPUnit\Framework\TestCase;

/**
 * Model with CAS-enabled fields for testing CAS check condition invariant.
 */
#[Item(
    table: 'pbt_cas_model',
    primaryIndex: new Index(hash: 'pk'),
)]
class CasModel
{
    #[Field(type: 'string', name: 'pk')]
    protected ?string $pk = null;

    #[Field(type: 'string', cas: Field::CAS_ENABLED)]
    protected mixed $casEnabled = null;

    #[Field(type: 'number', cas: Field::CAS_TIMESTAMP)]
    protected mixed $casTimestamp = null;

    #[Field(type: 'string')]
    protected mixed $normalField = null;
}

class CasCheckConditionTest extends TestCase
{
    use TestTrait;

    private ItemReflection $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ItemReflection(CasModel::class, []);
        $this->reflection->parse();
    }

    /**
     * Property 3: CAS Check Condition Invariant
     *
     * FOR ALL valid original data with CAS-enabled fields,
     * getCheckConditionData() SHALL return the original values for CAS fields.
     */
    public function testCheckConditionReturnsOriginalCasValues(): void
    {
        $this
            ->limitTo(200)
            ->forAll(
                Generators::suchThat(
                    fn(string $s) => $s !== '',
                    Generators::string(),
                ),
                Generators::string(),
                Generators::int(),
                Generators::string(),
            )
            ->then(function (string $pk, string $casEnabledVal, int $casTimestampVal, string $normalVal): void {
                // Build original data using DynamoDB attribute names
                $originalData = [
                    'pk'           => $pk,
                    'casEnabled'   => $casEnabledVal,
                    'casTimestamp'  => $casTimestampVal,
                    'normalField'  => $normalVal,
                ];

                // Hydrate an object from the data
                $obj = $this->reflection->hydrate($originalData);

                // Create managed state with original data
                $state = new ManagedItemState($this->reflection, $obj, $originalData);

                // Get check condition data
                $checkData = $state->getCheckConditionData();

                // CAS fields should have their original values
                $this->assertArrayHasKey('casEnabled', $checkData);
                // For string type, null→"" equivalence applies during hydrate,
                // but getCheckConditionData reads from originalData directly
                $this->assertSame(
                    $casEnabledVal,
                    $checkData['casEnabled'],
                    'CAS enabled field should preserve original value',
                );

                $this->assertArrayHasKey('casTimestamp', $checkData);
                $this->assertSame(
                    $casTimestampVal,
                    $checkData['casTimestamp'],
                    'CAS timestamp field should preserve original value',
                );

                // Normal (non-CAS) field should NOT appear in check condition data
                $this->assertArrayNotHasKey('normalField', $checkData);
                $this->assertArrayNotHasKey('pk', $checkData);
            });
    }
}
