<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/**
 * @template TScopedInstance of object
 * @template-covariant TTransformedValue
 */
interface Transformer {
	/**
	 * Transforms given element to the generic datatype.
	 *
	 * @param TElement        $element Either a scraped string element, a traced DOMElement, or a
	 *                                 parsed array from string element using Normalize helpers.
	 * @param TScopedInstance $scope
	 * @return TTransformedValue
	 *
	 * @throws InvalidSource When given $element type is not supported by the current transformer.
	 * @throws ScraperError  When cannot validate transformed data.
	 *
	 * @template TElement of string|non-empty-list|DOMElement
	 */
	public function transform( string|array|DOMElement $element, int $position, object $scope ): mixed;
}
