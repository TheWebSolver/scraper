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
 * @template ThReturn
 * @template TdReturn
 * @template-implements Transformer<CollectionSet<TdReturn>>
 */
class TableRowMarshaller implements Transformer {
	private const TR_NOT_FOUND = 'Impossible to find <tr> DOM Element in given %s.';

	/**
	 * @param TableTracer<ThReturn,TdReturn> $tracer
	 * @param class-string<Collectable>      $collectable
	 */
	public function __construct(
		private TableTracer $tracer,
		private string $collectable,
		private ?string $indexKey = null
	) {}

	public function transform( string|DOMElement $element, int $position ): mixed {
		$set   = $this->tracer->inferTableDataFrom( self::validate( $element )->childNodes );
		$names = $this->tracer->getColumnNames();

		count( $names ) === $this->tracer->getCurrentIterationCountOf( Table::Column )
			|| throw ScraperError::trigger(
				sprintf( $this->collectable::invalidCountMsg(), count( $names ), implode( '", "', $names ) )
				. ( ScraperError::getSource()?->errorMsg() ?? '' )
			);

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
