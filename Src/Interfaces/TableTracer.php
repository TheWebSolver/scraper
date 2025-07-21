<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Iterator;
use DOMElement;
use ArrayObject;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Attributes\CollectUsing;

/** @template TColumnReturn */
interface TableTracer extends Indexable {
	/** @placeholder `1:` static classname, `2:` throwing methodname, `3:` Table enum, `4:` Table case, `5`: EventAt enum, `6`: EventAt case, `7:` reason. */
	public const USE_EVENT_LISTENER = 'Invalid invocation of "%1$s::%2$s()". Use event listener for "%3$s::%4$s" and "%5$s::%6$s" to %7$s';

	/**
	 * Registers whether all tables present in the given source should be traced or not.
	 */
	public function withAllTables( bool $trace = true ): static;

	/**
	 * Registers targeted table structure(s) to be omitted from being traced.
	 *
	 * @no-named-arguments
	 */
	public function traceWithout( Table ...$targets ): static;

	/**
	 * Registers transformer for the targeted table structure.
	 *
	 * @param Transformer<contravariant static,TReturn> $transformer
	 * @template TReturn
	 */
	public function addTransformer( Table $for, Transformer $transformer ): static;

	/**
	 * Registers event listener for the targeted table structure and at the given event time.
	 *
	 * @param callable(TableTraced): void $callback
	 */
	public function addEventListener( Table $for, callable $callback, EventAt $eventAt = EventAt::Start ): static;

	/**
	 * Sets the source for mapping table column index keys, if any.
	 */
	public function setCollectorSource( CollectUsing $source ): void;

	/**
	 * Infers table(s) from given HTML content source.
	 *
	 * @param string|DOMElement $source    Either a HTML source or a table DOMElement.
	 * @param bool              $normalize When set to true, whitespaces/tabs/newlines and other
	 *                                     similar characters and controls must be cleaned.
	 * @throws InvalidSource When unsupported $source given, or no "table" in $source.
	 */
	public function inferTableFrom( string|DOMElement $source, bool $normalize ): void;

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
	 * @return array<TColumnReturn>
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
	 * @return array<Iterator<int,ArrayObject<array-key,TColumnReturn>>>
	 */
	public function getTableData(): array;

	/**
	 * Gets the source for mapping table column index keys, if any.
	 */
	public function getCollectorSource(): ?CollectUsing;

	/**
	 * Resets traced table structures' details.
	 *
	 * This may only be invoked after retrieving table columns' content Iterator
	 * and no further tracing is required of any remaining table structures.
	 */
	public function resetTableTraced(): void;

	/**
	 * Resets registered hooks such as event listeners and transformers.
	 *
	 * This may only be invoked after an iteration is complete to prevent side-effects
	 * of hooks not being applied to remaining items of an Iterator being iterated.
	 */
	public function resetTableHooks(): void;
}
