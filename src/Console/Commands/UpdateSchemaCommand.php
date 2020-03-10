<?php

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 18:41
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console\Commands;

use Oasis\Mlib\ODM\Dynamodb\DBAL\DriverManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateSchemaCommand extends AbstractSchemaCommand
{
    protected function configure()
    {
        parent::configure();

        $this->setName('odm:schema-tool:update')
            ->setDescription('Update the schema tables')
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                "dry run: prints out changes without really updating schema"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isDryRun = $input->getOption('dry-run');
        $schema   = DriverManager::getSchemaTool(
            $this->getItemManager(),
            $this->getManagedItemClasses(),
            [$output, "writeln"]
        );

        // create tables
        $schema->updateSchema($isDryRun);
    }
}
