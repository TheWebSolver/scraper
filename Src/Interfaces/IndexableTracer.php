<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use BackedEnum;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;

interface IndexableTracer {
	/** @placeholder: **1:** minimum expected items count, **2:** possible mappable keys. */
	public const INVALID_COUNT = 'Dataset count invalid. It must have atleast "%2$s" items mappable with keys: "%2$s".';

	/**
	 * Sets indices keyed to traced iterable items' value.
	 *
	 * If this method is used, items after last mappable key must be omitted from being traced.
	 *
	 * @param list<string> $indices   Strings used as keys for iterated items' value.
	 * @param int          ...$offset Skippable index/indices in between provided `$indices`, if any. For example:
	 *                                only three items needs to be mapped: `['one','three', 'five']`. But, there
	 *                                are `seven` traceable items. To properly map each index provided to its
	 *                                respective value, `$offset` indices must be passed: `0`, `2`, & `4`.
	 *
	 * @throws ScraperError When `$id` not found or provided before the target item is traced.
	 * @no-named-arguments
	 */
	public function setTracedItemsIndices( array $indices, int ...$offset ): void;

	/**
	 * Sets indices keyed to traced iterable items' value.
	 *
	 * @return list<string>
	 */
	public function getTracedItemsIndices(): array;

	/**
	 * Gets iteration's currently traced item's index, if provided.
	 *
	 * This must return null after current iteration is completed.
	 */
	public function getCurrentTracedItemIndex(): ?string;

	/**
	 * Gets current iteration count of items currently being iterated.
	 *
	 * This may return null after current iteration is completed.
	 *
	 * @param BackedEnum<T> $type            The item type being iterated if tracing includes multiple item types.
	 * @param bool          $offsetInclusive When this is set to true, the total count must include offset values
	 *                                       count even if provided offset indices have been omitted during trace.
	 * @template T of string|int
	 */
	public function getCurrentIterationCountOf( ?BackedEnum $type = null, bool $offsetInclusive = false ): ?int;
}
