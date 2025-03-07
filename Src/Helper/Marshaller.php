<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

use Closure;
use DOMElement;

class Marshaller {
	/** @var Closure(string|DomElement): string */
	private Closure $callback;
	/** @var string[] $content */
	private array $content;
	/** @var array{html:bool,node:bool} */
	private array $collect = array(
		'html' => false,
		'node' => false,
	);

	public function __construct( public readonly string $tagName ) {}

	/** @return array{html:bool,node:bool} */
	public function collectables(): array {
		return $this->collect;
	}

	/** @return string[] */
	public function content(): array {
		return $this->content;
	}

	public function collectHtml(): self {
		$this->collect['html'] = true;

		return $this;
	}

	public function collectElement(): self {
		$this->collect['element'] = true;

		return $this;
	}

	/** @param callable(string|DomElement): string $callback */
	public function marshallWith( callable $callback ): self {
		$this->callback = $callback( ... );

		return $this;
	}

	/** @return array{0:string,1?:string,2?:DomElement} */
	public function collect( string|DOMElement $element ): array {
		$content    = $element instanceof DOMElement ? $element->textContent : $element;
		$marshaller = $this->callback ?? null;
		$content    = trim(
			Normalize::nonBreakingSpaceToWhitespace( $marshaller ? $marshaller( $element ) : $content )
		);

		$collection = array( $content );

		if ( $this->isCollectable( $element, type: 'html' ) ) {
			$collection[1] = $element->ownerDocument?->saveHTML( $element ) ?: '';
		}

		if ( $this->isCollectable( $element, type: 'node' ) ) {
			$collection[2] = $element;
		}

		$this->content[] = $content;

		return $collection;
	}

	public function reset(): void {
		unset( $this->content );
	}

	/** @phpstan-assert-if-true =DOMElement $element */
	private function isCollectable( string|DOMElement $element, string $type ): bool {
		return $this->collect[ $type ] && $element instanceof DOMElement;
	}
}
