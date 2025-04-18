<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use BackedEnum;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;

interface Indexable {
	/** @placeholder: **1:** minimum expected items count, **2:** possible mappable keys. */
	public const INVALID_COUNT = 'Dataset count invalid. It must have atleast "%2$s" items mappable with keys: "%2$s".';

	/**
	 * Sets indices keyed to collected items' value.
	 *
	 * If this method is used, items after last mappable key must be omitted from being collected.
	 *
	 * @param list<string> $indices   Strings used as keys for iterated items' value.
	 * @param int          ...$offset Skippable index/indices in between provided `$indices`, if any. For example:
	 *                                only three items needs to be mapped: `['one','three', 'five']`. But, there
	 *                                are `seven` collectable items. To properly map each index provided to its
	 *                                respective value, `$offset` indices must be passed: `0`, `2`, & `4`.
	 *
	 * @throws ScraperError When `$id` not found or provided before the target item is collected.
	 * @no-named-arguments
	 */
	public function setItemsIndices( array $indices, int ...$offset ): void;

	/**
	 * Gets indices keyed to collected items' value.
	 *
	 * @return list<string>
	 */
	public function getItemsIndices(): array;

	/**
	 * Gets iteration's currently collected item's index, if provided.
	 *
	 * This must return null after current iteration is completed.
	 */
	public function getCurrentItemIndex(): ?string;

	/**
	 * Gets current iteration count of items currently being iterated.
	 *
	 * This may return null after current iteration is completed.
	 *
	 * @param BackedEnum<T> $type The item type being iterated if collection includes multiple item types.
	 * @template T of string|int
	 */
	public function getCurrentIterationCount( ?BackedEnum $type = null ): ?int;
}
