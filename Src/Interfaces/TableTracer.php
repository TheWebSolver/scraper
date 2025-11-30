<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Iterator;
use ArrayObject;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/**
 * @template TableColumnValue
 * @template-extends Traceable<ArrayObject<array-key,TableColumnValue>,TableTraced>
 */
interface TableTracer extends Traceable, Indexable {
	/** @placeholder `1:` static::methodName, `2:` Table::caseName, `3`: EventAt::caseName, `4:` reason. */
	public const USE_EVENT_LISTENER = 'Invalid invocation of "%1$s()". Use event listener for "%2$s" and "%3$s" to %4$s';
	/** @placeholder `%s:` Condition when table structure is required. */
	public const NO_TABLE_STRUCTURE_PROVIDED = 'Table structure is required for %s.';

	/**
	 * Registers whether all tables present in the given source should be traced or not.
	 */
	public function withAllTables( bool $trace = true ): static;

	/**
	 * Registers targeted table structure(s) to be omitted from being traced.
	 *
	 * @no-named-arguments
	 */
	public function traceWithout( Table ...$structures ): static;

	/**
	 * Infers table head content from the given element list.
	 *
	 * @param iterable<array-key,TElement> $elementList
	 * @throws InvalidSource When TElement is not a valid type.
	 * @template TElement
	 */
	public function inferTableHeadFrom( iterable $elementList ): void;

	/**
	 * Infers table columns' content as a dataset from the given element list.
	 *
	 * @param iterable<int,TElement> $elementList
	 * @return array<TableColumnValue>
	 * @throws InvalidSource When TElement is not a valid type.
	 * @template TElement
	 */
	public function inferTableDataFrom( iterable $elementList ): array;

	/**
	 * Gets traced table ID(s).
	 *
	 * @return ($current is true ? int|string : array<int,int|string>)
	 */
	public function getTableId( bool $current = false ): int|string|array;

	/**
	 * Gets traced table caption content indexed by respective table ID, if any.
	 *
	 * @return array<string|null>
	 */
	public function getTableCaption(): array;

	/**
	 * Gets traced table head content indexed by respective table ID.
	 *
	 * @return array<SplFixedArray<string>>
	 */
	public function getTableHead(): array;

	/**
	 * Gets traced table columns' content Iterator indexed by respective table ID.
	 *
	 * @return array<Iterator<array-key,ArrayObject<array-key,TableColumnValue>>>
	 */
	public function getTableData(): array;
}
