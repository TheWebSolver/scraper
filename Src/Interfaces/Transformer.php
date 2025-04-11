<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/** @template-covariant TTransformedValue */
interface Transformer {
	/**
	 * Transforms given element to the generic datatype.
	 *
	 * @param TableTracer<TColumnReturn> $tracer
	 * @return TTransformedValue
	 * @throws InvalidSource When $element is string and cannot be inferred to DOMElement.
	 * @throws ScraperError When cannot validate transformed data.
	 * @template TColumnReturn
	 */
	public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): mixed;
}
