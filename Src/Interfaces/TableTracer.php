<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Iterator;
use DOMElement;
use ArrayObject;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/**
 * @template ThReturn
 * @template TdReturn
 */
interface TableTracer {
	/**
	 * Sets whether all traced tables should be scanned or not.
	 */
	public function withAllTables( bool $trace = true ): static;

	/**
	 * Registers target table structure(s) to be omitted from being traced and inferred.
	 *
	 * @no-named-arguments
	 */
	public function traceWithout( Table ...$targets ): static;

	/**
	 * Registers transformers for targeted table structure.
	 *
	 * @param Transformer<TReturn> $transformer
	 * @template TReturn
	 */
	public function addTransformer( Table $for, Transformer $transformer ): static;

	/**
	 * Registers event listeners for targeted table structure.
	 *
	 * @param callable(static, string|DOMElement): void $callback
	 */
	public function addEventListener( Table $for, callable $callback ): static;

	/**
	 * Infers table(s) from given HTML content source.
	 *
	 * @param bool $normalize When set to true, whitespaces/tabs/newlines and other
	 *                        similar characters and controls must get cleaned.
	 */
	public function inferTableFrom( string $source, bool $normalize ): void;

	/**
	 * Infers table data from given element list.
	 *
	 * @param iterable<int,TElement> $elementList
	 * @return array<TdReturn>
	 * @throws InvalidSource When TElement is an unsupported item.
	 *
	 * @template TElement
	 */
	public function inferTableDataFrom( iterable $elementList ): array;

	/**
	 * Sets column names mappable to traced table dataset.
	 *
	 * @param list<string> $keys      Names to be used as index key for each mapped column. Extra columns after
	 *                                last mappable key will automatically be omitted from being inferred.
	 * @param int          $id        Usually, value of `$this->getTableId(current: true)`.
	 * @param int          ...$offset Skippable index/indices in between provided `$keys`, if any. For example:
	 *                                only three columns needs to be mapped: `['one','three', 'five']`. But, the
	 *                                table contains `seven` columns. To properly map each table column name to
	 *                                its respective value, `$offset` indices must be passed: `0`, `2`, & `4`.
	 *                                Last/seventh column at the sixth index will automatically gets omitted.
	 * @throws ScraperError When this method is invoked before any table is traced.
	 * @no-named-arguments
	 */
	public function setColumnNames( array $keys, int|string $id, int ...$offset ): void;

	/**
	 * Gets traced table IDs or current table being traced.
	 *
	 * @return ($current is true ? int|string : array<int|string>)
	 */
	public function getTableId( bool $current = false ): int|string|array;

	/**
	 * Gets column names mappable to traced table dataset.
	 *
	 * @return list<string>
	 */
	public function getColumnNames(): array;

	/**
	 * Gets collection of traced table dataset indexed by respective table ID.
	 *
	 * @return array<Iterator<int,ArrayObject<array-key,TdReturn>>>
	 * traced tables' iterable column set indexed by respective table ID.
	 */
	public function getTableData(): array;

	/**
	 * Gets table caption if not omitted from being traced.
	 *
	 * @return array<string|null> Traced tables' caption contents indexed by respective table ID.
	 */
	public function getTableCaption(): array;

	/**
	 * Gets table head if not omitted from being traced.
	 *
	 * @return ($namesOnly is true ? array<SplFixedArray<string>> : array<ArrayObject<int,ThReturn>>)
	 * Traced tables' head contents indexed by respective table ID.
	 */
	public function getTableHead( bool $namesOnly = false ): array;

	/**
	 * Gets current iteration table data column name, if set.
	 *
	 * This must return null after current iteration is completed.
	 */
	public function getCurrentColumnName(): ?string;

	/**
	 * Gets current iteration count of given element.
	 *
	 * This may return null after current iteration is completed.
	 *
	 * @param bool $offsetInclusive When this is set to true, the total count must include offset values
	 *                              count even if provided offset indices have been omitted during trace.
	 */
	public function getCurrentIterationCountOf( Table $element, bool $offsetInclusive = false ): ?int;

	/**
	 * Resets traced structures' details.
	 *
	 * This may only be invoked after retrieving a table Iterator and no further table tracing is required.
	 */
	public function resetTableTraced(): void;

	/**
	 * Resets registered hooks such as event listeners and transformers after trace completion.
	 *
	 * This may only be invoked after any iteration is complete to prevent side-effects
	 * of hooks not being applied to remaining items of an Iterator being iterated.
	 */
	public function resetTableHooks(): void;
}
