<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

use Closure;
use DOMElement;

class Marshaller {
	/** @var Closure(string|DomElement): string */
	private Closure $callback;
	/** @var array<int,string> $content */
	private array $content;
	/** @var array{html:bool,node:bool,onlyContent:bool} */
	private array $collect = array(
		'html'        => false,
		'node'        => false,
		'onlyContent' => false,
	);

	public function __construct( public readonly string $tagName ) {}

	/** @return array{html:bool,node:bool,onlyContent:bool} */
	public function collectables(): array {
		return $this->collect;
	}

	/** @return array<int,string> */
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

	public function onlyContent( bool $toCollect = true ): self {
		$this->collect['onlyContent'] = $toCollect;

		return $this;
	}

	/** @param callable(string|DomElement): string $callback */
	public function marshallWith( callable $callback ): self {
		$this->callback = $callback( ... );

		return $this;
	}

	/** @return string|array{0:string,1?:string,2?:DomElement} */
	public function collect( string|DOMElement $element ): string|array {
		$content  = $element instanceof DOMElement ? $element->textContent : $element;
		$marshall = $this->callback ?? null;
		$content  = trim(
			Normalize::nonBreakingSpaceToWhitespace( $marshall ? $marshall( $element ) : $content )
		);

		$this->content[] = $content;

		if ( $this->collect['onlyContent'] ) {
			return $content;
		}

		$collection = array( $content );

		$this->isCollectable( $element, type: 'html' )
			&& ( $collection[1] = $element->ownerDocument?->saveHTML( $element ) ?: '' );

		$this->isCollectable( $element, type: 'node' ) && ( $collection[2] = $element );

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
