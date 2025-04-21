<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use BackedEnum;

interface Indexable {
	/** @placeholder: **1:** minimum expected items count, **2:** possible mappable keys. */
	public const INVALID_COUNT = 'Dataset count invalid. It must have atleast "%2$s" items mappable with keys: "%2$s".';

	/**
	 * Sets indices to be used as collected items' keys.
	 *
	 * If this method is used, remaining items after last mappable key must be omitted from being collected.
	 *
	 * @param list<string> $indices   Strings used as keys for iterated items' value.
	 * @param int          ...$offset Skippable index/indices in between provided `$indices`, if any. For example:
	 *                                only three items needs to be mapped: `['one','three', 'five']`. But, there
	 *                                are `seven` collectable items. To properly map each index provided to its
	 *                                respective value, `$offset` indices must be passed: `0`, `2`, & `4`.
	 *
	 * @no-named-arguments
	 */
	public function setItemsIndices( array $indices, int ...$offset ): void;

	/**
	 * Gets indices to be used as collected items' keys.
	 *
	 * @return list<string>
	 */
	public function getItemsIndices(): array;

	/**
	 * Gets current iteration index of an item being collected.
	 *
	 * This must return one of the indices, if provided, with `$this->setItemsIndices()`.
	 * This must return null after current iteration is complete.
	 */
	public function getCurrentItemIndex(): ?string;

	/**
	 * Gets current iteration count of items currently being collected.
	 *
	 * This may return null after current iteration is complete.
	 *
	 * @param ?BackedEnum<T> $type The item type being collected if collection includes multiple item types.
	 *                             For Eg: `$type` in a table are: `Table::Row`, `Table::Column`.
	 * @template T of string|int
	 */
	public function getCurrentIterationCount( ?BackedEnum $type = null ): ?int;
}
