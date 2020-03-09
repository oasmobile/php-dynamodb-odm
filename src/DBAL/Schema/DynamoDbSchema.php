<?php
/**
 * Created by PhpStorm.
 * User: xuchang
 * Date: 2020-03-09
 * Time: 12:00
 */

namespace Oasis\Mlib\ODM\Dynamodb\DBAL\Schema;

use Aws\DynamoDb\Exception\DynamoDbException;
use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
use Oasis\Mlib\AwsWrappers\DynamoDbManager;
use Oasis\Mlib\AwsWrappers\DynamoDbTable;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Index;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;

use function GuzzleHttp\Promise\all;

/**
 * Class DynamoDbSchema
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL\Schema
 */
class DynamoDbSchema extends AbstractSchema
{

    public function createSchema($skipExisting, $dryRun)
    {
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamodbConfig());

        $classes = $this->getManagedItemClasses();
        foreach ($classes as $class => $reflection) {
            $tableName = $this->itemManager->getDefaultTablePrefix().$reflection->getTableName();
            if ($dynamoManager->listTables(sprintf("/^%s\$/", preg_quote($tableName, "/")))) {
                if (!$skipExisting && !$dryRun) {
                    throw new ODMException("Table ".$tableName." already exists!");
                }
            }
        }

        $waits = [];

        /**
         * @var  $class
         * @var ItemReflection $reflection
         */
        foreach ($classes as $class => $reflection) {
            $itemDef = $reflection->getItemDefinition();
            if ($itemDef->projected) {
                $this->outputWrite(sprintf("Class %s is projected class, will not create table.", $class));
                continue;
            }

            $attributeTypes   = $reflection->getAttributeTypes();
            $fieldNameMapping = $reflection->getFieldNameMapping();

            $lsis = [];
            /** @var Index $localSecondaryIndex */
            foreach ($itemDef->localSecondaryIndices as $localSecondaryIndex) {
                $lsis[] = $localSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);
            }
            $gsis = [];
            /** @var Index $globalSecondaryIndex */
            foreach ($itemDef->globalSecondaryIndices as $globalSecondaryIndex) {
                $gsis[] = $globalSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);
            }

            $tableName = $this->itemManager->getDefaultTablePrefix().$reflection->getTableName();

            $this->outputWrite("Will create table <info>$tableName</info> for class <info>$class</info> ...");
            if (!$dryRun) {
                $dynamoManager->createTable(
                    $tableName,
                    $itemDef->primaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes),
                    $lsis,
                    $gsis
                );

                if ($gsis) {
                    // if there is gsi, we nee to wait before creating next table
                    $this->outputWrite("Will wait for GSI creation ...");
                    $dynamoManager->waitForTablesToBeFullyReady($tableName, 60, 2);
                }
                else {
                    $waits[] = $dynamoManager->waitForTableCreation(
                        $tableName,
                        60,
                        1,
                        false
                    );
                }
                $this->outputWrite('Created.');
            }
        }

        if (!$dryRun) {
            $this->outputWrite("Waiting for all tables to be active ...");
            all($waits)->wait();
            $this->outputWrite("Done.");
        }
    }

    /** @noinspection PhpStatementHasEmptyBodyInspection */
    public function updateSchema($isDryRun)
    {
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamodbConfig());
        $classes       = $this->getManagedItemClasses();
        $classCreation = [];
        $gsiChanges    = [];
        $im            = $this->itemManager;

        /** @var ItemReflection $reflection */
        foreach ($classes as $class => $reflection) {
            if ($reflection->getItemDefinition()->projected) {
                // will skip projected table
                continue;
            }
            $tableName = $this->itemManager->getDefaultTablePrefix().$reflection->getTableName();

            if (!$dynamoManager->listTables(sprintf("/^%s\$/", preg_quote($tableName, "/")))) {
                // will create
                $classCreation[] = function () use ($isDryRun, $im, $class, $reflection, $dynamoManager, $tableName) {
                    $itemDef          = $reflection->getItemDefinition();
                    $attributeTypes   = $reflection->getAttributeTypes();
                    $fieldNameMapping = $reflection->getFieldNameMapping();

                    $lsis = [];
                    foreach ($itemDef->localSecondaryIndices as $localSecondaryIndex) {
                        $lsis[] = $localSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);
                    }
                    $gsis = [];
                    foreach ($itemDef->globalSecondaryIndices as $globalSecondaryIndex) {
                        $gsis[] = $globalSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);
                    }

                    $tableName = $im->getDefaultTablePrefix().$reflection->getTableName();

                    $this->outputWrite("Will create table <info>$tableName</info> for class <info>$class</info>.");

                    if (!$isDryRun) {
                        $dynamoManager->createTable(
                            $tableName,
                            $itemDef->primaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes),
                            $lsis,
                            $gsis
                        );
                        $this->outputWrite('Created.');
                    }

                    return $tableName;
                };
            }
            else {
                // will update
                $table            = new DynamoDbTable($this->getItemManager()->getDynamodbConfig(), $tableName);
                $itemDef          = $reflection->getItemDefinition();
                $attributeTypes   = $reflection->getAttributeTypes();
                $fieldNameMapping = $reflection->getFieldNameMapping();

                $oldPrimaryIndex = $table->getPrimaryIndex();
                $primaryIndex    = $itemDef->primaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);

                if (!$oldPrimaryIndex->equals($primaryIndex)) {
                    throw new ODMException(
                        sprintf(
                            "Primary index changed, which is not possible when table is already created! [Table = %s]",
                            $tableName
                        )
                    );
                }

                $oldLsis = $table->getLocalSecondaryIndices();
                foreach ($itemDef->localSecondaryIndices as $localSecondaryIndex) {
                    $idx = $localSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);
                    if (!isset($oldLsis[$idx->getName()])) {
                        throw new ODMException(
                            sprintf(
                                "LSI named %s did not exist, you cannot update LSI when table is created! [Table = %s]",
                                $idx->getName(),
                                $tableName
                            )
                        );
                    }
                    else {
                        unset($oldLsis[$idx->getName()]);
                    }
                }
                if ($oldLsis) {
                    throw new ODMException(
                        sprintf(
                            "LSI named %s removed, you cannot remove any LSI when table is created!",
                            implode(",", array_keys($oldLsis))
                        )
                    );
                }

                $oldGsis = $table->getGlobalSecondaryIndices();
                foreach ($itemDef->globalSecondaryIndices as $globalSecondaryIndex) {
                    $idx = $globalSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);

                    if (!isset($oldGsis[$idx->getName()])) {
                        // new GSI
                        $gsiChanges[] = function () use (
                            $isDryRun,
                            $dynamoManager,
                            $class,
                            $tableName,
                            $table,
                            $idx
                        ) {
                            $this->outputWrite(
                                "Will add GSI ["
                                .$idx->getName()
                                ."] to table <info>$tableName</info> for class <info>$class</info> ..."
                            );
                            if (!$isDryRun) {
                                $table->addGlobalSecondaryIndex($idx);
                                // if there is gsi alteration, we nee to wait before continue
                                $this->outputWrite("Will wait for creation of GSI ".$idx->getName()." ...");
                                $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                $this->outputWrite('Done.');
                            }

                            return $tableName;
                        };
                    }
                    else {
                        // GSI with same name

                        if ($idx->equals($oldGsis[$idx->getName()])) {
                            // nothing to update
                        }
                        else {
                            $gsiChanges[] = function () use (
                                $isDryRun,
                                $dynamoManager,
                                $class,
                                $tableName,
                                $table,
                                $idx
                            ) {
                                $this->outputWrite(
                                    "Will update GSI ["
                                    .$idx->getName()
                                    ."] on table <info>$tableName</info> for class <info>$class</info> ..."
                                );
                                if (!$isDryRun) {
                                    // if there is gsi alteration, we nee to wait before continue
                                    $table->deleteGlobalSecondaryIndex($idx->getName());
                                    $this->outputWrite("Will wait for deletion of GSI ".$idx->getName()." ...");
                                    $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                    //$output->writeln(
                                    //    "Will sleep 3 seconds before creating new GSI. If the creation fails, you can feel free to run update command again."
                                    //);
                                    //sleep(3);
                                    $table->addGlobalSecondaryIndex($idx);
                                    $this->outputWrite("Will wait for creation of GSI ".$idx->getName()." ...");
                                    $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                    $this->outputWrite('Done.');
                                }

                                return $tableName;
                            };
                        }

                        unset($oldGsis[$idx->getName()]);
                    }
                }
                if ($oldGsis) {
                    /** @var DynamoDbIndex $removedGsi */
                    foreach ($oldGsis as $removedGsi) {
                        $gsiChanges[] = function () use (
                            $isDryRun,
                            $dynamoManager,
                            $class,
                            $tableName,
                            $table,
                            $removedGsi
                        ) {
                            $this->outputWrite(
                                "Will remove GSI ["
                                .$removedGsi->getName()
                                ."] from table <info>$tableName</info> for class <info>$class</info> ..."
                            );
                            if (!$isDryRun) {
                                $table->deleteGlobalSecondaryIndex($removedGsi->getName());
                                $this->outputWrite("Will wait for deletion of GSI ".$removedGsi->getName()." ...");
                                $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                $this->outputWrite('Done.');
                            }

                            return $tableName;
                        };
                    }
                }
            }
        }

        if (!$classCreation && !$gsiChanges) {
            $this->outputWrite("Nothing to change.");
        }
        else {
            $waits = [];
            foreach ($classCreation as $callable) {
                $tableName = call_user_func($callable);
                if (!$isDryRun) {
                    $waits[] = $dynamoManager->waitForTableCreation($tableName, 60, 1, false);
                }
            }
            if ($waits) {
                $this->outputWrite("Waiting for all created tables to be active ...");
                all($waits)->wait();
                $this->outputWrite("Done.");
            }

            $changedTables = [];
            foreach ($gsiChanges as $callable) {
                $tableName = call_user_func($callable);
                if (!$isDryRun) {
                    $changedTables[] = $tableName;
                }
            }
        }
    }

    public function dropSchema()
    {
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamodbConfig());
        $classes       = $this->getManagedItemClasses();
        $im            = $this->getItemManager();

        $waits = [];
        foreach ($classes as $class => $reflection) {
            $tableName = $im->getDefaultTablePrefix().$reflection->getTableName();
            $this->outputWrite("Will drop table <info>$tableName</info> for class <info>$class</info> ...");
            try {
                $dynamoManager->deleteTable($tableName);
            } catch (DynamoDbException $e) {
                if ("ResourceNotFoundException" == $e->getAwsErrorCode()) {
                    $this->outputWrite('<error>Table not found.</error>');
                }
                else {
                    throw $e;
                }
            }
            $waits[] = $dynamoManager->waitForTableDeletion(
                $tableName,
                60,
                1,
                false
            );
            $this->outputWrite('Deleted.');
        }
        $this->outputWrite("Waiting for all tables to be inactive");
        all($waits)->wait();
        $this->outputWrite("Done.");
    }
}
