<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<string> */
class TableColumnMarshaller implements Transformer {
	/** @param list<string> $collectable Subset of column names to be collected. */
	public function __construct( private readonly array $collectable = array() ) {}

	/** @param TableTracer<mixed,string> $tracer */
	public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): string {
		$content = trim( $element instanceof DOMElement ? $element->textContent : $element );

		return $this->columnNameExistsInCollectable( $tracer ) ? $content : '';
	}

	/** @param TableTracer<mixed,string> $tracer */
	protected function columnNameExistsInCollectable( TableTracer $tracer ): bool {
		return ! $this->collectable
			|| ! ( $columnName = $tracer->getCurrentColumnName() )
			|| in_array( $columnName, $this->collectable, strict: true );
	}
}
