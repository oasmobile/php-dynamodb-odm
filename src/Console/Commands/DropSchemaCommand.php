<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 18:41
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DropSchemaCommand extends AbstractSchemaCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->setName('odm:schema-tool:drop')
            ->setDescription('Drop the schema tables');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemaTool = $this->getItemManager()->createDBConnection()->getSchemaTool(
            $this->getItemManager(),
            $this->getManagedItemClasses(),
            [$output, "writeln"]
        );

        // create tables
        $schemaTool->dropSchema();

        return 0;
    }
}
