<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<object,string> */
class MarshallItem implements Transformer {
	/** @placeholder **%s:** The scoped object where marshaller is used. */
	final public const INVALID_ARRAY = '"%s" only supports normalized array with 5 items, content being 4th item on 3rd index.';

	public function transform( string|array|DOMElement $element, object $scope ): string {
		$transformed = match ( true ) {
			$element instanceof DOMElement => $element->textContent,
			is_string( $element )          => $element,
			default                        => is_string( $contentPart = ( $element[3] ?? null ) )
				? $contentPart
				: throw new InvalidSource( sprintf( self::INVALID_ARRAY, $scope::class ) )
		};

		return trim( $transformed );
	}
}
