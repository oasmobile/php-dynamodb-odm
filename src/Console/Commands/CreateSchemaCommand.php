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
use Symfony\Component\Console\Output\OutputInterface;

class CreateSchemaCommand extends AbstractSchemaCommand
{
    
    protected function configure()
    {
        parent::configure();
        
        $this->setName('odm:schema-tool:create')
             ->setDescription('Processes the schema and create corresponding tables and indices.');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $im            = $this->getItemManager();
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamodbConfig());
        
        $classes = [];
        foreach ($this->getClasses() as $class) {
            try {
                $reflection = $im->getItemReflection($class);
            } catch (\Exception $e) {
                continue;
            }
            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();
            if ($dynamoManager->listTables(sprintf("/%s/", preg_quote($tableName, "/")))) {
                throw new ODMException("Table " . $tableName . " already exists!");
            }
            $classes[] = $class;
        }
        
        foreach ($classes as $class) {
            $reflection     = $im->getItemReflection($class);
            $itemDef        = $reflection->getItemDefinition();
            $attributeTypes = $reflection->getAttributeTypes();
            
            $lsis = [];
            foreach ($itemDef->localSecondaryIndices as $localSecondaryIndex) {
                $lsis[] = $localSecondaryIndex->getDynamodbIndex($attributeTypes);
            }
            $gsis = [];
            foreach ($itemDef->globalSecondaryIndices as $globalSecondaryIndex) {
                $gsis[] = $globalSecondaryIndex->getDynamodbIndex($attributeTypes);
            }
            
            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();
            $dynamoManager->createTable(
                $tableName,
                $itemDef->primaryIndex->getDynamodbIndex($attributeTypes),
                $lsis,
                $gsis
            );
        }
    }
    
}
