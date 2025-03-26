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
use TheWebSolver\Codegarage\Scraper\Interfaces\Collectable;
use TheWebSolver\Codegarage\Scraper\Interfaces\TableTracer;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/**
 * @template TdReturn
 * @template-implements Transformer<CollectionSet<TdReturn>>
 */
class TableRowMarshaller implements Transformer {
	private const TR_NOT_FOUND = 'Impossible to find <tr> DOM Element in given %s.';

	/** @param class-string<Collectable> $collectable */
	public function __construct( private string $collectable, private ?string $indexKey = null ) {}

	/**
	 * @param TableTracer<mixed,TdReturn> $tracer
	 * @throws ScraperError When cannot validate transformed data.
	 */
	public function transform( string|DOMElement $element, int $position, TableTracer $tracer ): CollectionSet {
		$set   = $tracer->inferTableDataFrom( self::validate( $element )->childNodes );
		$names = $tracer->getColumnNames();
		$msg   = $this->collectable::invalidCountMsg();

		count( $names ) === $tracer->getCurrentIterationCountOf( Table::Column )
			|| ScraperError::withSourceMsg( $msg, count( $names ), implode( '", "', $names ) );

		return new CollectionSet( $this->discoverIndexKeyFrom( $set ) ?? $position, new ArrayObject( $set ) );
	}

	/** @throws InvalidSource When given $element is not <tr> or does not have child nodes. */
	public static function validate( string|DOMElement $element ): DOMElement {
		if ( ! $element instanceof DOMElement ) {
			$el   = AssertDOMElement::inferredFrom( $element, type: 'tr' );
			$type = 'string';
		} else {
			$el   = AssertDOMElement::isValid( $element, type: 'tr' ) ? $element : null;
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
}
