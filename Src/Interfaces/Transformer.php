<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Interfaces;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

/**
 * @template TScope of object
 * @template-covariant TransformedValue
 */
interface Transformer {
	/**
	 * Transforms given element.
	 *
	 * @param TElement $element Either a scraped string element, a traced DOMElement, or a
	 *                          parsed array from string element using Normalize helpers.
	 * @param TScope   $scope   The scoped class instance where transformer is being used.
	 * @return TransformedValue
	 *
	 * @throws InvalidSource When given $element type is not supported by the transformer.
	 * @throws ScraperError  When data could not be transformed.
	 *
	 * @template TElement of string|non-empty-list|DOMElement
	 */
	public function transform( string|array|DOMElement $element, object $scope ): mixed;
}
