<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<TableTracer<string>,string> */
class TableColumnMarshaller implements Transformer {
	public function transform( string|array|DOMElement $element, object $scope ): string {
		$transformed = match ( true ) {
			$element instanceof DOMElement => $element->textContent,
			is_string( $element )          => $element,
			default                        => is_string( $contentPart = ( $element[3] ?? null ) )
				? $contentPart
				: throw new InvalidSource(
					sprintf( '"%s" only supports normalized Table Column array.', self::class )
				)
		};

		return trim( $transformed );
	}
}
