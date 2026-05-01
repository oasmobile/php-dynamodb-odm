<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Integration;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\DynamoDbSchemaTool;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\Ut\CasDemoUser;
use Oasis\Mlib\ODM\Dynamodb\Ut\UTConfig;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for DynamoDbSchemaTool: create, update, drop operations.
 *
 * These tests use the CasDemoUser model which maps to a simple table (cas-demo-users)
 * with only a primary index, making schema operations fast and predictable.
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
     * Test the full schema lifecycle: drop (cleanup) → create → update (idempotent) → drop.
     */
    public function testSchemaCreateUpdateDrop(): void
    {
        $schemaTool = $this->getSchemaTool();

        // Step 1: Drop any existing table (cleanup from previous test runs)
        $this->outputLog = [];
        $schemaTool->dropSchema();
        $this->assertNotEmpty($this->outputLog, 'dropSchema should produce output');

        // Step 2: Create schema
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

        // Cleanup the item
        $this->itemManager->remove($fetched);
        $this->itemManager->setSkipCheckAndSet(true);
        $this->itemManager->flush();
        $this->itemManager->setSkipCheckAndSet(false);

        // Step 3: Update schema (should be idempotent — "Nothing to change")
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

        // Step 4: Drop schema
        $this->outputLog = [];
        $schemaTool->dropSchema();
        $this->assertNotEmpty($this->outputLog, 'dropSchema should produce output');

        // Step 5: Re-create with skip-existing (should not throw even though table doesn't exist)
        $this->outputLog = [];
        $schemaTool->createSchema(true, false);
        $this->assertNotEmpty($this->outputLog);

        // Final cleanup: drop again
        $schemaTool->dropSchema();
    }

    /**
     * Test createSchema with dry-run mode: no actual table creation.
     */
    public function testCreateSchemaDryRun(): void
    {
        $schemaTool = $this->getSchemaTool();

        // Ensure table doesn't exist
        $schemaTool->dropSchema();

        // Dry run should produce output but not actually create the table
        $this->outputLog = [];
        $schemaTool->createSchema(false, true);
        $this->assertNotEmpty($this->outputLog, 'Dry run should produce output');

        // Verify table was NOT created: get should fail or return null
        // Re-create for real so we can verify dry-run didn't create it
        // We do this by trying updateSchema which would detect the table doesn't exist
        $this->outputLog = [];
        $schemaTool->updateSchema(false);
        // updateSchema should try to create the table since it doesn't exist
        $hasCreate = false;
        foreach ($this->outputLog as $line) {
            if (str_contains($line, 'create') || str_contains($line, 'Will create')) {
                $hasCreate = true;
                break;
            }
        }
        $this->assertTrue($hasCreate, 'After dry-run create, updateSchema should detect table needs creation');

        // Cleanup
        $schemaTool->dropSchema();
    }

    /**
     * Test updateSchema with dry-run mode.
     */
    public function testUpdateSchemaDryRun(): void
    {
        $schemaTool = $this->getSchemaTool();

        // Ensure table exists
        $schemaTool->dropSchema();
        $schemaTool->createSchema(false, false);

        // Dry run update should report nothing to change
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

        // Cleanup
        $schemaTool->dropSchema();
    }
}
