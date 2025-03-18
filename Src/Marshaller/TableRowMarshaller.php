<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use Closure;
use DOMNode;
use DOMElement;
use ArrayObject;
use TheWebSolver\Codegarage\Scraper\AssertDOMElement;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<ArrayObject<array-key,string>|DOMNode[]> */
class TableRowMarshaller implements Transformer {
	/** @var Closure(string|DOMElement): (ArrayObject<array-key,string>|DOMNode[]) */
	private Closure $marshaller;

	public function marshallWith( callable $callback ): void {
		$this->marshaller = $callback( ... );
	}

	public function collect( string|DOMElement $element, bool $onlyContent = true ): mixed {
		if ( isset( $this->marshaller ) ) {
			return ( $this->marshaller )( $element );
		}

		return Normalize::nodesToArray( self::validate( $element )->childNodes );
	}

	public function collectHtml(): void {}
	public function collectElement(): void {}
	public function collectables(): array {
		return array();
	}
	public function getContent(): array {
		return array();
	}

	public function flushContent(): void {}

	/** @throws InvalidSource When given $element does not have Table Data <td>. */
	public static function validate( string|DOMElement $element, int $dataCount = 0 ): DOMElement {
		if ( ! $element instanceof DOMElement ) {
			$element = DOMDocumentFactory::createFromHtml( $element )->firstChild;
		}

		return $element && AssertDOMElement::isValid( $element, $dataCount )
			? $element
			: throw new InvalidSource( 'Impossible to infer as <tr> DOM Element from given string.' );
	}
}
