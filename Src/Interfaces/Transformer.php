<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/** @template TReturn */
interface Transformer {
	/**
	 * Transforms element using the provided callback.
	 *
	 * @param callable(string|DomElement, int): TReturn $callback
	 * @return self<TReturn>
	 */
	public function with( callable $callback ): self;

	/**
	 * Transforms given element to the generic datatype.
	 *
	 * @return TReturn
	 * @throws InvalidSource When $element is string and cannot be inferred to DOMElement.
	 */
	public function transform( string|DOMElement $element, int $position ): mixed;
}
