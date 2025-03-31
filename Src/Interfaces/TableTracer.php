<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use DOMNode;
use Iterator;
use ArrayObject;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;

/**
 * @template ThReturn
 * @template TdReturn
 */
interface TableTracer {
	/**
	 * Sets whether all found tables should be scanned or not.
	 */
	public function withAllTables( bool $trace = true ): static;

	/**
	 * Registers target table structure(s) to not be traced and inferred.
	 *
	 * @no-named-arguments
	 */
	public function traceWithout( Table ...$targets ): static;

	/**
	 * Registers transformers to transform traced element.
	 *
	 * Each call made to this method must override previously set transformer, if any.
	 * Also, each call must only override for its respective offset/index key.
	 *
	 * @param Transformer<TReturn> $transformer
	 * @template TReturn
	 */
	public function transformWith( Transformer $transformer, Table $target ): static;

	/**
	 * Registers event listener for targeted table structure.
	 *
	 * @param callable( static ): void $eventListener
	 */
	public function subscribeWith( callable $eventListener, Table $target ): static;

	/**
	 * Traces table from given element list.
	 *
	 * @param iterable<int,TContent> $elementList
	 * @template TContent of string|DOMNode
	 */
	public function inferTableFrom( iterable $elementList ): void;

	/**
	 * Infers table head from given element list.
	 *
	 * @param iterable<int,TElement> $elementList
	 * @return ?array{0:list<string>,1:list<ThReturn>}
	 * @template TElement of string|DOMNode
	 */
	public function inferTableHeadFrom( iterable $elementList ): ?array;

	/**
	 * Infers table data from given element list.
	 *
	 * @param iterable<int,TElement> $elementList
	 * @return TdReturn[]
	 * @template TElement of string|DOMNode
	 */
	public function inferTableDataFrom( iterable $elementList ): array;

	/**
	 * Sets index keys mappable to traced table data set.
	 *
	 * @param list<string> $keys      Names to be used as index key for each mapped column. Extra columns after
	 *                                last mappable key will automatically be omitted from being inferred.
	 * @param int          $id        Usually, value of `$this->getTableId(current: true)`.
	 * @param int          ...$offset Skippable index/indices in between provided `$keys`, if any. For example:
	 *                                only three columns needs to be mapped: `['one','three', 'five']`. But, the
	 *                                table contains `seven` columns. To properly map each table column name to
	 *                                its respective value, `$offset` indices must be passed: `0`, `2`, & `4`.
	 *                                Last/seventh column at the sixth index will automatically gets omitted.
	 * @throws ScraperError When this method is invoked before table is discovered.
	 * @no-named-arguments
	 */
	public function setColumnNames( array $keys, int $id, int ...$offset ): void;

	/**
	 * Gets traced table IDs or current table being traced.
	 *
	 * @return ($current is true ? int : int[])
	 */
	public function getTableId( bool $current = false ): int|array;

	/**
	 * Gets index keys mappable to traced table data set.
	 *
	 * @return list<string>
	 */
	public function getColumnNames(): array;

	/**
	 * Gets collection of traced table head indexed by respective table ID.
	 *
	 * @return ($namesOnly is true ? array<int,SplFixedArray<string>> : array<int,ArrayObject<int,ThReturn>>)
	 */
	public function getTableHead( bool $namesOnly = false ): array;

	/**
	 * Gets collection of traced table data indexed by respective table ID.
	 *
	 * @return array<int,Iterator<int,ArrayObject<array-key,TdReturn>>>
	 */
	public function getTableData(): array;

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
}
