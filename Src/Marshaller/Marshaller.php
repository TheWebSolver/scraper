<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<string> */
class Marshaller implements Transformer {
	public function transform( string|DOMElement $element, int $position ): string {
		return trim( $element instanceof DOMElement ? $element->textContent : $element );
	}
}
