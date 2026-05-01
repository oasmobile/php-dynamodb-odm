<?php
declare(strict_types=1);

namespace Oasis\Mlib\ODM\Dynamodb\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Item
{
    public readonly string $table;
    public readonly Index $primaryIndex;
    /** @var Index[] */
    public readonly array $globalSecondaryIndices;
    /** @var Index[] */
    public readonly array $localSecondaryIndices;
    public readonly ?string $repository;
    public readonly bool $projected;

    public function __construct(
        string $table,
        array|Index $primaryIndex,
        array $globalSecondaryIndices = [],
        array $localSecondaryIndices = [],
        ?string $repository = null,
        bool $projected = false,
    ) {
        $this->table = $table;
        $this->primaryIndex = $primaryIndex instanceof Index
            ? $primaryIndex
            : new Index(...$primaryIndex);
        $this->globalSecondaryIndices = array_map(
            fn($idx) => $idx instanceof Index ? $idx : new Index(...$idx),
            $globalSecondaryIndices,
        );
        $this->localSecondaryIndices = array_map(
            fn($idx) => $idx instanceof Index ? $idx : new Index(...$idx),
            $localSecondaryIndices,
        );
        $this->repository = $repository;
        $this->projected = $projected;
    }
}
