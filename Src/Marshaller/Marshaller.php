<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<string> */
class TableColumnMarshaller implements Transformer {
	/** @param TableTracer<mixed,string> $tracer */
	public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): string {
		return trim( $element instanceof DOMElement ? $element->textContent : $element );
	}
}
