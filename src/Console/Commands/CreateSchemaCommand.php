<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 17:40
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console\Commands;

use Oasis\Mlib\ODM\Dynamodb\DBAL\DriverManager;
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
            ->addOption('skip-existing-table', null, InputOption::VALUE_NONE, "skip creating existing table!")
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                "output possible table creations without actually creating them."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $skipExisting = $input->getOption('skip-existing-table');
        $dryRun       = $input->getOption('dry-run');

        $schemaTool = DriverManager::getSchemaTool(
            $this->getItemManager(),
            $this->getManagedItemClasses(),
            [$output, "writeln"]
        );

        // create tables
        $schemaTool->createSchema($skipExisting, $dryRun);
    }

}
