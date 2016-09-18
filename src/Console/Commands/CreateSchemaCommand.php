<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 17:40
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console\Commands;

use Oasis\Mlib\AwsWrappers\DynamoDbManager;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateSchemaCommand extends AbstractSchemaCommand
{
    
    protected function configure()
    {
        parent::configure();
        
        $this->setName('odm:schema-tool:create')
             ->setDescription('Processes the schema and create corresponding tables and indices.')
             ->addOption('skip-existing-table', null, InputOption::VALUE_NONE, "skip creating existing table!");;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $skipExisting  = $input->getOption('skip-existing-table');
        $im            = $this->getItemManager();
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamodbConfig());
        
        $classes = $this->getValidClasses();
        foreach ($classes as $class => $reflection) {
            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();
            if ($dynamoManager->listTables(sprintf("/%s/", preg_quote($tableName, "/")))) {
                if (!$skipExisting) {
                    throw new ODMException("Table " . $tableName . " already exists!");
                }
            }
        }
        
        foreach ($classes as $class => $reflection) {
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
            
            $dynamoManager->createTable(
                $tableName,
                $itemDef->primaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes),
                $lsis,
                $gsis
            );
            
            $output->writeln('Done.');
        }
    }
    
}
