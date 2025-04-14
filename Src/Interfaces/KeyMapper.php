<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use BackedEnum;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

interface KeyMapper {
	/** @placeholder: **1:** minimum expected items count, **2:** possible mappable keys. */
	public const INVALID_COUNT = 'Dataset count invalid. It must have atleast "%2$s" items mappable with keys: "%2$s".';

	/**
	 * Sets mappable keys to be used an index key of respective item in collected dataset.
	 *
	 * @param class-string<BackedEnum>|list<string> $keys     Keys that are used as index key mapped to collected dataset.
	 * @param string|BackedEnum|null                $indexKey One of the `$keys` whose value to be used as dataset index.
	 *
	 * @throws InvalidSource When $keys is passed as a `string` but not a `BackedEnum` classname.
	 */
	public function useKeys( string|array $keys, string|BackedEnum|null $indexKey = null ): static;

	/**
	 * Gets mappable keys to be used an index key of respective item in collected dataset.
	 *
	 * @return list<string>
	 */
	public function getKeys(): array;

	/**
	 * Gets the index key whose value to be used as collected dataset's index.
	 */
	public function getIndexKey(): ?string;
}
