<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use Closure;
use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<ArrayObject<array-key,string>|DOMElement> */
class TableRowMarshaller implements Transformer {
	/** @var Closure(string|DOMElement, int): (ArrayObject<array-key,string>|DOMElement) */
	private Closure $marshaller;

	public function with( callable $callback ): static {
		$this->marshaller = $callback( ... );

		return $this;
	}

	public function transform( string|DOMElement $element, int $position ): ArrayObject|DOMElement {
		if ( isset( $this->marshaller ) ) {
			return ( $this->marshaller )( $element, $position );
		}

		return self::validate( $element );
	}

	/** @throws InvalidSource When given $element is not <tr> or does not have child nodes. */
	public static function validate( string|DOMElement $element ): DOMElement {
		$element instanceof DOMElement || $element = self::inferFrom( $element );

		return AssertDOMElement::isValid( $element, type: 'tr' )
			? $element
			: throw new InvalidSource( 'Impossible to infer as <tr> DOM Element from given string.' );
	}

	/** @throws InvalidSource When given $value does not contain <tr> HTML Element. */
	public static function inferFrom( string $value ): ?DOMElement {
		$dom = DOMDocumentFactory::createFromHtml( $value );
		$tr  = null;

		if ( ! $dom->childNodes->length ) {
			throw new InvalidSource( 'Given string does not contain <tr> HTML Element.' );
		}

		foreach ( $dom->childNodes as $node ) {
			if ( AssertDOMElement::isValid( $node, type: 'tr' ) ) {
				$tr = $node;

				break;
			}
		}

		return $tr;
	}
}
