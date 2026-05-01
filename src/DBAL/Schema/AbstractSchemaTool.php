<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: xuchang
 * Date: 2020-03-09
 * Time: 12:00
 */

namespace Oasis\Mlib\ODM\Dynamodb\DBAL\Schema;

use Oasis\Mlib\ODM\Dynamodb\ItemManager;

/**
 * Class AbstractSchema
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL\Schema
 */
abstract class AbstractSchemaTool
{
    /** @var callable|null */
    protected mixed $outputFunction;

    public function __construct(
        protected ItemManager $itemManager,
        protected array $classReflections,
        ?callable $outputFunction = null,
    ) {
        $this->outputFunction = $outputFunction;
    }

    abstract public function createSchema(bool $skipExisting, bool $dryRun): void;

    abstract public function updateSchema(bool $isDryRun): void;

    abstract public function dropSchema(): void;

    protected function getItemManager(): ItemManager
    {
        return $this->itemManager;
    }

    protected function getManagedItemClasses(): array
    {
        return $this->classReflections;
    }

    protected function outputWrite(string $message): void
    {
        if (is_callable($this->outputFunction)) {
            $output = $this->outputFunction;
            $output($message);
        }
        else {
            mnotice($message);
        }
    }


}
