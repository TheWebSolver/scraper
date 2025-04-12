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
	 * @param TScopedInstance $scope
	 * @return TTransformedValue
	 * @throws InvalidSource When $element is string and cannot be inferred to DOMElement.
	 * @throws ScraperError When cannot validate transformed data.
	 */
	public function transform( string|DOMElement $element, int $position, object $scope ): mixed;
}
