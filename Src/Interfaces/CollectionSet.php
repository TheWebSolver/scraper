<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use BackedEnum;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

interface KeyMapper {
	/**
	 * Sets keys to be used for a collection set.
	 *
	 * @param class-string<BackedEnum>|list<string> $keys     Keys that are used for collecting data as a single set.
	 * @param string|BackedEnum|null                $indexKey One of the `$keys` whose value to be used as the key.
	 *                                                        If provided, it must be used as the collection key.
	 * @throws InvalidSource When $keys is string but not a `BackedEnum` classname.
	 */
	public function useKeys( string|array $keys, string|BackedEnum|null $indexKey = null ): static;

	/**
	 * Gets keys to be used as a collection set.
	 *
	 * @return list<string>
	 */
	public function getKeys(): array;

	/**
	 * Gets the index key used for the collection set.
	 */
	public function getIndexKey(): ?string;
}
