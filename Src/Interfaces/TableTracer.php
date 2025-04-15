<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Iterator;
use DOMElement;
use ArrayObject;
use SplFixedArray;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/** @template TColumnReturn */
interface TableTracer extends IndexableTracer {
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
	 * @param Transformer<contravariant static,TReturn> $transformer
	 * @template TReturn
	 */
	public function addTransformer( Table $for, Transformer $transformer ): static;

	/**
	 * Registers event listeners for targeted table structure for both start or finish tracing.
	 *
	 * @param callable(static, string|DOMElement): void $callback
	 * @param bool                                      $finish Whether to listen for callback before or after tracing is finished.
	 */
	public function addEventListener( Table $for, callable $callback, bool $finish = false ): static;

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
	 * @return array<TColumnReturn>
	 * @throws InvalidSource When TElement is an unsupported item.
	 *
	 * @template TElement
	 */
	public function inferTableDataFrom( iterable $elementList ): array;

	/**
	 * Gets traced table IDs or current table being traced.
	 *
	 * @return ($current is true ? int|string : array<int|string>)
	 */
	public function getTableId( bool $current = false ): int|string|array;

	/**
	 * Gets collection of traced table dataset indexed by respective table ID.
	 *
	 * @return array<Iterator<int,ArrayObject<array-key,TColumnReturn>>>
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
	 * @return array<SplFixedArray<string>>
	 * Traced tables' head contents indexed by respective table ID.
	 */
	public function getTableHead(): array;

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
