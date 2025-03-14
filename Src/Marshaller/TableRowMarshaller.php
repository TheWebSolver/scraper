<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use Closure;
use DOMNode;
use Countable;
use DOMElement;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;
use TheWebSolver\Codegarage\Scraper\DOMDocumentFactory;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<?DOMNode[]> */
class TableRowMarshaller implements Transformer {
	/** @var Closure(string|DOMElement): ?DOMNode[] */
	private Closure $marshaller;

	public function __construct( private readonly int|Countable $dataCount ) {}

	public function marshallWith( callable $callback ): void {
		$this->marshaller = $callback( ... );
	}

	public function collect( string|DOMElement $element, bool $onlyContent = true ): mixed {
		if ( isset( $this->marshaller ) ) {
			return ( $this->marshaller )( $element );
		}

		if ( ! $element instanceof DOMElement ) {
			$element = DOMDocumentFactory::createFromHtml( $element )->firstChild;
		}

		return $this->isCollectable( $element ) ? Normalize::nodesToArray( $element->childNodes ) : null;
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

	/** @phpstan-assert-if-true =DOMElement $element */
	private function isCollectable( mixed $element ): bool {
		return $element instanceof DOMElement && $element->childNodes->count() >= $this->dataCount;
	}
}
