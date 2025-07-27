<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use BackedEnum;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;

interface Indexable {
	/** @placeholder: **1:** minimum expected items count, **2:** possible mappable keys. */
	public const INVALID_COUNT = 'Dataset count invalid. It must have atleast "%1$s" items mappable with keys: "%2$s".';

	/**
	 * Sets indices to be used as collected items' keys.
	 *
	 * If this method is used, remaining items after last mappable key must be omitted from being collected.
	 */
	public function setItemsIndices( CollectUsing $source ): void;

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
