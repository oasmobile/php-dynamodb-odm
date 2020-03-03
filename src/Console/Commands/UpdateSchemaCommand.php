<?php /** @noinspection PhpStatementHasEmptyBodyInspection */

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 18:41
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console\Commands;

use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
use Oasis\Mlib\AwsWrappers\DynamoDbManager;
use Oasis\Mlib\AwsWrappers\DynamoDbTable;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function GuzzleHttp\Promise\all;

class UpdateSchemaCommand extends AbstractSchemaCommand
{
    protected function configure()
    {
        parent::configure();
        
        $this->setName('odm:schema-tool:update')
             ->setDescription('Update the dynamodb tables')
             ->addOption(
                 'dry-run',
                 'd',
                 InputOption::VALUE_NONE,
                 "dry run: prints out changes without really updating schema"
             );
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isDryRun      = $input->getOption('dry-run');
        $classes       = $this->getManagedItemClasses();
        $im            = $this->getItemManager();
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamodbConfig());
        
        $classCreation = [];
        $gsiChanges    = [];
        /** @var ItemReflection $reflection */
        foreach ($classes as $class => $reflection) {
            if ($reflection->getItemDefinition()->projected) {
                // will skip projected table
                continue;
            }
            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();

            if (!$dynamoManager->listTables(sprintf("/^%s\$/", preg_quote($tableName, "/")))) {
                // will create
                $classCreation[] = function () use (
                    $isDryRun,
                    $im,
                    $output,
                    $class,
                    $reflection,
                    $dynamoManager,
                    $tableName
                ) {
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
                    
                    $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();
                    
                    $output->writeln("Will create table <info>$tableName</info> for class <info>$class</info> ...");
                    
                    if (!$isDryRun) {
                        $dynamoManager->createTable(
                            $tableName,
                            $itemDef->primaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes),
                            $lsis,
                            $gsis
                        );
                        
                        $output->writeln('Created.');
                    }
                    
                    return $tableName;
                };
            }
            else {
                // will update
                $table = new DynamoDbTable($this->getItemManager()->getDynamodbConfig(), $tableName);
                
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
                            $output,
                            $class,
                            $tableName,
                            $table,
                            $idx
                        ) {
                            $output->writeln(
                                "Will add GSI ["
                                . $idx->getName()
                                . "] to table <info>$tableName</info> for class <info>$class</info> ..."
                            );
                            if (!$isDryRun) {
                                $table->addGlobalSecondaryIndex($idx);
                                // if there is gsi alteration, we nee to wait before continue
                                $output->writeln("Will wait for creation of GSI " . $idx->getName() . " ...");
                                $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                $output->writeln('Done.');
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
                                $output,
                                $class,
                                $tableName,
                                $table,
                                $idx
                            ) {
                                $output->writeln(
                                    "Will update GSI ["
                                    . $idx->getName()
                                    . "] on table <info>$tableName</info> for class <info>$class</info> ..."
                                );
                                if (!$isDryRun) {
                                    // if there is gsi alteration, we nee to wait before continue
                                    $table->deleteGlobalSecondaryIndex($idx->getName());
                                    $output->writeln("Will wait for deletion of GSI " . $idx->getName() . " ...");
                                    $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                    //$output->writeln(
                                    //    "Will sleep 3 seconds before creating new GSI. If the creation fails, you can feel free to run update command again."
                                    //);
                                    //sleep(3);
                                    $table->addGlobalSecondaryIndex($idx);
                                    $output->writeln("Will wait for creation of GSI " . $idx->getName() . " ...");
                                    $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                    $output->writeln('Done.');
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
                            $output,
                            $class,
                            $tableName,
                            $table,
                            $removedGsi
                        ) {
                            $output->writeln(
                                "Will remove GSI ["
                                . $removedGsi->getName()
                                . "] from table <info>$tableName</info> for class <info>$class</info> ..."
                            );
                            if (!$isDryRun) {
                                $table->deleteGlobalSecondaryIndex($removedGsi->getName());
                                $output->writeln("Will wait for deletion of GSI " . $removedGsi->getName() . " ...");
                                $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                $output->writeln('Done.');
                            }
                            
                            return $tableName;
                        };
                        
                    }
                }
            }
        }
        
        if (!$classCreation && !$gsiChanges) {
            $output->writeln("Nothing to change.");
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
                $output->writeln("Waiting for all created tables to be active ...");
                all($waits)->wait();
                $output->writeln("Done.");
            }
            
            $changedTables = [];
            foreach ($gsiChanges as $callable) {
                $tableName = call_user_func($callable);
                if (!$isDryRun) {
                    $changedTables[] = $tableName;
                }
            }
            //if ($changedTables) {
            //    $output->writeln("Waiting for all GSI modifications to be active ...");
            //    $dynamoManager->waitForTablesToBeFullyReady($changedTables, 180, 5);
            //    $output->writeln("Done.");
            //}
        }
    }
}
