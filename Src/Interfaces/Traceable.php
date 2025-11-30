<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use Iterator;
use BackedEnum;
use DOMElement;
use TheWebSolver\Codegarage\Scraper\Enums\EventAt;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/**
 * @template TracedDataset
 * @template TracedEvent of object
 */
interface Traceable {
	/**
	 * Registers transformer for the targeted data structure.
	 *
	 * @param Transformer $transformer Transformer to transform traced data.
	 * @param ?BackedEnum $structure   The Structure to target, or null if traceable data has no structure.
	 */
	public function addTransformer( Transformer $transformer, ?BackedEnum $structure = null ): static;

	/**
	 * Registers event listener for the targeted structure and at the given event time.
	 *
	 * @param callable(TracedEvent): void $listener  The event listener callback.
	 * @param ?BackedEnum                 $structure The structure to target, or null if traceable data has no structure.
	 * @param EventAt                     $eventAt   The event time to listen at.
	 */
	public function addEventListener( callable $listener, ?BackedEnum $structure = null, EventAt $eventAt = EventAt::Start ): static;

	/**
	 * Infers data from traced source.
	 *
	 * @param string|DOMElement $source    Either a string or HTML DOMElement source.
	 * @param bool              $normalize When set to true, whitespaces/tabs/newlines and other
	 *                                     similar characters and controls must be cleaned.
	 * @throws InvalidSource When unsupported $source given, or source has no traceable data.
	 */
	public function inferFrom( string|DOMElement $source, bool $normalize ): void;

	/**
	 * Gets inferred data from traced source.
	 *
	 * @return Iterator<array-key,TracedDataset>
	 */
	public function getData(): Iterator;

	/**
	 * Ensures whether transformer has been added for the given structure.
	 *
	 * @param ?BackedEnum $structure The structure to target, or null if traceable data has no structure.
	 */
	public function hasTransformer( ?BackedEnum $structure = null ): bool;

	/**
	 * Resets traced structures' details.
	 *
	 * This may only be invoked after retrieving traced and inferred data Iterator
	 * and no further tracing is required of any remaining traceable data.
	 */
	public function resetTraced(): void;

	/**
	 * Resets registered hooks such as event listeners and transformers.
	 *
	 * This may only be invoked after an iteration is complete to prevent side-effects
	 * of hooks not being applied to remaining items of an Iterator being iterated.
	 */
	public function resetHooks(): void;
}
