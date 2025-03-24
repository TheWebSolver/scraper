<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use DOMNode;
use Iterator;
use ArrayObject;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;

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
	 * Registers transformers to transformed traced element.
	 *
	 * Each call made to this method must override previously set transformer, if any.
	 * Also, each call must only override for its respective index key.
	 *
	 * @param array{
	 *   tr ?: Transformer<CollectionSet<TdReturn>|iterable<int,string|DOMNode>>,
	 *   th ?: Transformer<ThReturn>,
	 *   td ?: Transformer<TdReturn>
	 * } $transformers
	 */
	public function withTransformers( array $transformers ): static;

	/**
	 * Traces table from given element list.
	 *
	 * @param iterable<int,TContent> $elementList
	 * @template TContent of string|DOMNode
	 */
	public function traceTableIn( iterable $elementList ): void;

	/**
	 * Traces table data from given element list.
	 *
	 * @param iterable<int,TElement> $elementList
	 * @return TdReturn[]
	 * @template TElement of string|DOMNode
	 */
	public function inferTableDataFrom( iterable $elementList ): array;

	/**
	 * Sets index keys mappable to traced table data set.
	 *
	 * @param list<string> $keys
	 */
	public function setColumnNames( array $keys ): void;

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
	 * Gets index key of traced table row's currently iterated data.
	 *
	 * This must return null when current iteration is completed.
	 */
	public function getCurrentColumnName(): ?string;

	/**
	 * Gets current iteration count of given element.
	 */
	public function getCurrentIterationCountOf( Table $element ): int;
}
