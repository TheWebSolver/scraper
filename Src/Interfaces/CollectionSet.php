<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

interface CollectionSet {
	/**
	 * Sets keys to be used for a collection set.
	 *
	 * @param class-string<Collectable>|string[] $keys     Keys that're used for collecting data as a single set.
	 * @param string|Collectable|null            $indexKey One of the `$keys` whose value to be used as the key.
	 *                                                     If provided, it must be used as the collection key.
	 * @throws InvalidSource When $keys is string but not a `Collectable` enum name.
	 */
	public function useKeys( string|array $keys, string|Collectable|null $indexKey = null ): void;

	/**
	 * Gets keys to be used as a collection set.
	 *
	 * @return string[]
	 */
	public function getKeys(): array;

	/**
	 * Gets the index key used for the collection set.
	 */
	public function getIndexKey(): ?string;
}
