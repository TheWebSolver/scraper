<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/**
 * @template TdReturn
 * @template-implements Transformer<CollectionSet<TdReturn>>
 */
class TableRowMarshaller implements Transformer {
	private const TR_NOT_FOUND = 'Impossible to find <tr> DOM Element in given %s.';

	/**
	 * @param string  $invalidCountMsg The exception msg when table column names count does not match with inferred data
	 *                                 count. If this is not provided, column names count verification will be skipped.
	 * @param ?string $indexKey        The column to use as the index/keys for the inferred dataset. This works
	 *                                 the same way as using `array_column` function's [#3] param $index_key.
	 */
	public function __construct( private string $invalidCountMsg = '', private ?string $indexKey = null ) {}

	/**
	 * @param TableTracer<mixed,TdReturn> $tracer
	 * @throws ScraperError When cannot validate transformed data.
	 */
	public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): CollectionSet {
		$set = $tracer->inferTableDataFrom( self::validate( $element )->childNodes );

		$this->invalidCountMsg && $this->validateColumnNamesCount( $tracer );

		return new CollectionSet( $this->discoverIndexKeyFrom( $set ) ?? $position, new ArrayObject( $set ) );
	}

	/** @throws InvalidSource When given $element is not <tr> or does not have child nodes. */
	public static function validate( string|DOMElement $element ): DOMElement {
		if ( ! $element instanceof DOMElement ) {
			$el   = AssertDOMElement::inferredFrom( $element, Table::Row, normalize: false );
			$type = 'string';
		} else {
			$el   = AssertDOMElement::isValid( $element, Table::Row ) ? $element : null;
			$type = "<{$element->tagName}> element";
		}

		return $el ?? throw new InvalidSource( sprintf( self::TR_NOT_FOUND, $type ) );
	}

	/** @param TdReturn[] $dataset */
	protected function discoverIndexKeyFrom( array $dataset ): string|int|null {
		if ( ! $this->indexKey ) {
			return null;
		}

		$value = $dataset[ $this->indexKey ] ?? null;

		return is_string( $value ) || is_int( $value ) ? $value : null;
	}

	/** @param TableTracer<mixed,TdReturn> $tracer */
	private function validateColumnNamesCount( TableTracer $tracer ): void {
		$count = count( $names = $tracer->getColumnNames() );

		$count === $tracer->getCurrentIterationCountOf( Table::Column )
			|| ScraperError::withSourceMsg( $this->invalidCountMsg, $count, implode( '", "', $names ) );
	}
}
