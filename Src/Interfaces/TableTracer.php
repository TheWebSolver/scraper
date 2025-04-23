<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Iterator;
use ArrayObject;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Event\TableTraced;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/** @template TColumnReturn */
interface TableTracer extends Indexable {
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
	 * Registers transformer for targeted table structure.
	 *
	 * @param Transformer<contravariant static,TReturn> $transformer
	 * @template TReturn
	 */
	public function addTransformer( Table $for, Transformer $transformer ): static;

	/**
	 * Registers event listener for targeted table structure.
	 *
	 * @param callable(TableTraced): void $callback
	 */
	public function addEventListener( Table $for, callable $callback, EventAt $eventAt = EventAt::Start ): static;

	/**
	 * Infers table(s) from given HTML content source.
	 *
	 * @param bool $normalize When set to true, whitespaces/tabs/newlines and other
	 *                        similar characters and controls must get cleaned.
	 */
	public function inferTableFrom( string $source, bool $normalize ): void;

	/**
	 * Infers table head content from given element list.
	 *
	 * @param iterable<array-key,TElement> $elementList
	 * @throws InvalidSource When TElement is not a valid type.
	 * @template TElement
	 */
	public function inferTableHeadFrom( iterable $elementList ): void;

	/**
	 * Infers table column data from given element list.
	 *
	 * @param iterable<int,TElement> $elementList
	 * @return array<TColumnReturn>
	 * @throws InvalidSource When TElement is not a valid type.
	 *
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
	 * Gets collection of traced table columns's data indexed by respective table ID.
	 *
	 * @return array<Iterator<int,ArrayObject<array-key,TColumnReturn>>>
	 */
	public function getTableData(): array;

	/**
	 * Gets traced table caption data indexed by respective table ID.
	 *
	 * @return array<string|null>
	 */
	public function getTableCaption(): array;

	/**
	 * Gets traced table head data indexed by respective table ID.
	 *
	 * @return array<SplFixedArray<string>>
	 */
	public function getTableHead(): array;

	/**
	 * Resets traced structures' details.
	 *
	 * This may only be invoked after retrieving a table Iterator and no further table tracing is required.
	 */
	public function resetTableTraced(): void;

	/**
	 * Resets registered hooks such as event listeners and transformers.
	 *
	 * This may only be invoked after any iteration is complete to prevent side-effects
	 * of hooks not being applied to remaining items of an Iterator being iterated.
	 */
	public function resetTableHooks(): void;
}
