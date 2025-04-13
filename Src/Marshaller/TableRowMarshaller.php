<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMNode;
use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\Data\CollectionSet;
use TheWebSolver\Codegarage\Scraper\Error\ScraperError;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/**
 * @template TColumnReturn
 * @template-implements Transformer<TableTracer<TColumnReturn>,CollectionSet<TColumnReturn>>
 */
class TableRowMarshaller implements Transformer {
	private const TABLE_ROW_NOT_FOUND = 'Impossible to find <tr> DOM Element in given %s.';

	/**
	 * @param string  $invalidCountMsg The exception msg when table column names count does not match with inferred data
	 *                                 count. If this is not provided, column names count verification will be skipped.
	 * @param ?string $indexKey        The column to use as the index/keys for the inferred dataset. This works
	 *                                 the same way as using `array_column` function's [#3] param $index_key.
	 */
	public function __construct( private string $invalidCountMsg = '', private ?string $indexKey = null ) {}

	public function transform( string|array|DOMElement $element, object $tracer ): CollectionSet {
		$set      = $tracer->inferTableDataFrom( self::validate( $element ) );
		$count    = $tracer->getCurrentIterationCountOf( Table::Column );
		$position = $count ? $count - 1 : 0;

		$this->invalidCountMsg && $this->validateColumnNamesCount( $tracer, defaultCount: count( $set ) );

		return new CollectionSet( $this->discoverIndexKeyFrom( $set ) ?? $position, new ArrayObject( $set ) );
	}

	/**
	 * @param string|mixed[]|DOMElement $element
	 * @return iterable<int,DOMNode|list<array{0:string,1:string,2:string,3:string,4:string}>>
	 * @throws InvalidSource When given $element is not <tr> or does not have child nodes.
	 */
	public static function validate( string|array|DOMElement $element ): iterable {
		$tr = Table::Row->value;

		if ( is_string( $element ) ) {
			[$matched, $columns] = Normalize::tableColumnsFrom( $element );
			$el                  = $matched ? $columns : null;
			$type                = 'string';
		} elseif ( $element instanceof DOMElement ) {
			$el   = AssertDOMElement::isValid( $element, $tr ) ? $element->childNodes->getIterator() : null;
			$type = "<{$element->tagName}> DOMElement";
		} else {
			$el   = $element;
			$type = 'array';
		}

		return $el ?? throw new InvalidSource( sprintf( self::TABLE_ROW_NOT_FOUND, $type ) );
	}

	/** @param TColumnReturn[] $dataset */
	protected function discoverIndexKeyFrom( array $dataset ): string|int|null {
		if ( ! $this->indexKey ) {
			return null;
		}

		$value = $dataset[ $this->indexKey ] ?? null;

		return is_string( $value ) || is_int( $value ) ? $value : null;
	}

	/** @param TableTracer<TColumnReturn> $tracer */
	private function validateColumnNamesCount( TableTracer $tracer, int $defaultCount ): void {
		$count = count( $names = $tracer->getColumnNames() ) ?: $defaultCount;

		$count === $tracer->getCurrentIterationCountOf( Table::Column )
			|| ScraperError::withSourceMsg( $this->invalidCountMsg, $count, implode( '", "', $names ) );
	}
}
