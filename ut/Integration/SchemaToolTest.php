<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Integration;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\DynamoDbSchemaTool;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\Ut\CasDemoUser;
use Oasis\Mlib\ODM\Dynamodb\Ut\UTConfig;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for DynamoDbSchemaTool: create, update, drop operations.
 *
 * Tests are chained via #[Depends] to reuse schema state and avoid redundant
 * drop/create cycles. Each DynamoDB create/delete + wait costs ~3-4s, so
 * minimising the number of real schema operations is critical for speed.
 *
 * Chain: testCreateSchema          → table ACTIVE
 *      → testUpdateSchemaIdempotent → table ACTIVE
 *      → testUpdateSchemaDryRun    → table ACTIVE
 *      → testCreateSchemaSkipExistingOnExistingTable → table ACTIVE (skipped)
 *      → testDropSchema            → table ABSENT
 *      → testCreateSchemaDryRun    → table ABSENT (dry-run doesn't create)
 *      → testCreateSchemaSkipExisting → table ACTIVE (created via skipExisting)
 *      → testFinalCleanup          → table ABSENT
 */
class SchemaToolTest extends TestCase
{
    protected ItemManager $itemManager;
    protected array $classReflections;
    protected array $outputLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->itemManager = new ItemManager(
            UTConfig::$dynamodbConfig, UTConfig::$tablePrefix
        );
        $reflection = $this->itemManager->getItemReflection(CasDemoUser::class);
        $this->classReflections = [CasDemoUser::class => $reflection];
        $this->outputLog = [];
    }

    protected function getOutputCallback(): callable
    {
        return function (string $message): void {
            $this->outputLog[] = $message;
        };
    }

    protected function getSchemaTool(): DynamoDbSchemaTool
    {
        $connection = $this->itemManager->createDBConnection();
        $tool = $connection->getSchemaTool(
            $this->itemManager,
            $this->classReflections,
            $this->getOutputCallback()
        );
        $this->assertInstanceOf(DynamoDbSchemaTool::class, $tool);

        return $tool;
    }

    /**
     * Drop any leftover table, then create schema and verify it is usable.
     * Leaves the table in ACTIVE state for subsequent tests.
     */
    public function testCreateSchema(): void
    {
        $schemaTool = $this->getSchemaTool();

        // Cleanup from previous test runs
        $schemaTool->dropSchema();

        // Create schema
        $this->outputLog = [];
        $schemaTool->createSchema(false, false);
        $this->assertNotEmpty($this->outputLog, 'createSchema should produce output');

        // Verify table is usable: persist and get an item
        $user = new CasDemoUser();
        $user->id = mt_rand(1000, PHP_INT_MAX);
        $user->name = 'SchemaTest';
        $this->itemManager->persist($user);
        $this->itemManager->flush();
        $this->itemManager->clear();

        $fetched = $this->itemManager->get(CasDemoUser::class, ['id' => $user->id], true);
        $this->assertNotNull($fetched);
        $this->assertEquals('SchemaTest', $fetched->name);

        // Cleanup the item (leave table intact)
        $this->itemManager->remove($fetched);
        $this->itemManager->setSkipCheckAndSet(true);
        $this->itemManager->flush();
        $this->itemManager->setSkipCheckAndSet(false);
    }

    /**
     * Update schema on an unchanged table — should report "Nothing to change".
     * Table remains in ACTIVE state.
     */
    #[Depends('testCreateSchema')]
    public function testUpdateSchemaIdempotent(): void
    {
        $schemaTool = $this->getSchemaTool();

        $this->outputLog = [];
        $schemaTool->updateSchema(false);
        $hasNothingToChange = false;
        foreach ($this->outputLog as $line) {
            if (str_contains($line, 'Nothing to change')) {
                $hasNothingToChange = true;
                break;
            }
        }
        $this->assertTrue($hasNothingToChange, 'updateSchema on unchanged schema should report "Nothing to change"');
    }

    /**
     * Dry-run updateSchema on an existing table — should report "Nothing to change"
     * without modifying anything. Table remains in ACTIVE state.
     */
    #[Depends('testUpdateSchemaIdempotent')]
    public function testUpdateSchemaDryRun(): void
    {
        $schemaTool = $this->getSchemaTool();

        $this->outputLog = [];
        $schemaTool->updateSchema(true);
        $hasNothingToChange = false;
        foreach ($this->outputLog as $line) {
            if (str_contains($line, 'Nothing to change')) {
                $hasNothingToChange = true;
                break;
            }
        }
        $this->assertTrue($hasNothingToChange, 'Dry-run updateSchema on unchanged schema should report "Nothing to change"');
    }

    /**
     * createSchema with skipExisting=true when the table already exists —
     * should skip without error or attempting to create. Table remains ACTIVE.
     */
    #[Depends('testUpdateSchemaDryRun')]
    public function testCreateSchemaSkipExistingOnExistingTable(): void
    {
        $schemaTool = $this->getSchemaTool();

        $this->outputLog = [];
        $schemaTool->createSchema(true, false);
        $this->assertNotEmpty($this->outputLog);

        // Verify the output says "skipping" (not "Created")
        $hasSkipping = false;
        foreach ($this->outputLog as $line) {
            if (str_contains($line, 'skipping') || str_contains($line, 'already exists')) {
                $hasSkipping = true;
                break;
            }
        }
        $this->assertTrue($hasSkipping, 'createSchema(skipExisting=true) on existing table should report skipping');
    }

    /**
     * Drop the table. Leaves the table absent for the next test.
     */
    #[Depends('testCreateSchemaSkipExistingOnExistingTable')]
    public function testDropSchema(): void
    {
        $schemaTool = $this->getSchemaTool();

        $this->outputLog = [];
        $schemaTool->dropSchema();
        $this->assertNotEmpty($this->outputLog, 'dropSchema should produce output');
    }

    /**
     * Dry-run createSchema when the table does not exist — should produce output
     * but NOT actually create the table. Verified by running updateSchema (dry-run)
     * which detects the table needs creation. Table remains absent.
     */
    #[Depends('testDropSchema')]
    public function testCreateSchemaDryRun(): void
    {
        $schemaTool = $this->getSchemaTool();

        // Dry run should produce output but not actually create the table
        $this->outputLog = [];
        $schemaTool->createSchema(false, true);
        $this->assertNotEmpty($this->outputLog, 'Dry run should produce output');

        // Verify table was NOT created: dry-run updateSchema should detect it needs creation
        $this->outputLog = [];
        $schemaTool->updateSchema(true);
        $hasCreate = false;
        foreach ($this->outputLog as $line) {
            if (str_contains($line, 'create') || str_contains($line, 'Will create')) {
                $hasCreate = true;
                break;
            }
        }
        $this->assertTrue($hasCreate, 'After dry-run create, updateSchema should detect table needs creation');
    }

    /**
     * createSchema with skipExisting=true when the table does not exist —
     * should create the table without error. Table becomes ACTIVE.
     */
    #[Depends('testCreateSchemaDryRun')]
    public function testCreateSchemaSkipExisting(): void
    {
        $schemaTool = $this->getSchemaTool();

        $this->outputLog = [];
        $schemaTool->createSchema(true, false);
        $this->assertNotEmpty($this->outputLog);
    }

    /**
     * Final cleanup: drop the table created by testCreateSchemaSkipExisting.
     */
    #[Depends('testCreateSchemaSkipExisting')]
    public function testFinalCleanup(): void
    {
        $schemaTool = $this->getSchemaTool();

        $this->outputLog = [];
        $schemaTool->dropSchema();
        $this->assertNotEmpty($this->outputLog, 'dropSchema should produce output');
    }
}
