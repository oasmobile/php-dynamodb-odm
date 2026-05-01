<?php
// Feature: release-2.0, Property 5: Index Keys Consistency
// Validates: Requirements 11.6

namespace Oasis\Mlib\ODM\Dynamodb\Ut\PBT;

use Eris\Generators;
use Eris\TestTrait;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use PHPUnit\Framework\TestCase;

class IndexKeysConsistencyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 5: Index Keys Consistency
     *
     * FOR ALL valid hash and range combinations,
     * getKeys() SHALL return an array containing exactly the hash key
     * and optionally the range key.
     *
     * When range is non-empty: exactly 2 keys (hash, range).
     * When range is empty: exactly 1 key (hash).
     */
    public function testIndexKeysConsistency(): void
    {
        $this
            ->limitTo(200)
            ->forAll(
                Generators::suchThat(
                    fn(string $s) => $s !== '',
                    Generators::string(),
                ),
                Generators::string(),  // range can be empty or non-empty
            )
            ->then(function (string $hash, string $range): void {
                $index = new Index(hash: $hash, range: $range);
                $keys  = $index->getKeys();

                if ($range !== '') {
                    // Non-empty range: should return exactly 2 keys
                    $this->assertCount(2, $keys, 'Index with hash+range should return exactly 2 keys');
                    $this->assertSame($hash, $keys[0], 'First key should be the hash key');
                    $this->assertSame($range, $keys[1], 'Second key should be the range key');
                } else {
                    // Empty range: should return exactly 1 key
                    $this->assertCount(1, $keys, 'Index with hash only should return exactly 1 key');
                    $this->assertSame($hash, $keys[0], 'The single key should be the hash key');
                }
            });
    }

    /**
     * Dedicated test for hash-only Index (range always empty).
     */
    public function testIndexKeysHashOnly(): void
    {
        $this
            ->limitTo(200)
            ->forAll(
                Generators::suchThat(
                    fn(string $s) => $s !== '',
                    Generators::string(),
                ),
            )
            ->then(function (string $hash): void {
                $index = new Index(hash: $hash);
                $keys  = $index->getKeys();

                $this->assertCount(1, $keys, 'Index with hash only should return exactly 1 key');
                $this->assertSame($hash, $keys[0], 'The single key should be the hash key');
            });
    }
}
