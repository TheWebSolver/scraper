<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

use Closure;
use DOMElement;

class Marshaller {
	final public const COLLECT_HTML         = 'html';
	final public const COLLECT_NODE         = 'node';
	final public const COLLECT_ONLY_CONTENT = 'onlyContent';

	/** @var Closure(string|DomElement): string */
	private Closure $callback;
	/** @var array<int,string> $content */
	private array $content;
	/** @var array<self::COLLECT_*,bool> */
	private array $collect = array(
		self::COLLECT_HTML         => false,
		self::COLLECT_NODE         => false,
		self::COLLECT_ONLY_CONTENT => false,
	);

	public function __construct( public readonly string $tagName ) {}

	/** @return array<self::COLLECT_*,bool> */
	public function collectables(): array {
		return $this->collect;
	}

	/** @return array<int,string> */
	public function content(): array {
		return $this->content ?? array();
	}

	public function collectHtml(): self {
		$this->collect[ self::COLLECT_HTML ] = true;

		return $this;
	}

	public function collectElement(): self {
		$this->collect[ self::COLLECT_NODE ] = true;

		return $this;
	}

	public function onlyContent( bool $toCollect = true ): self {
		$this->collect[ self::COLLECT_ONLY_CONTENT ] = $toCollect;

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

		if ( $this->collect[ self::COLLECT_ONLY_CONTENT ] ) {
			return $content;
		}

		$collection = array( $content );

		$this->isCollectable( $element, type: self::COLLECT_HTML )
			&& ( $collection[1] = $element->ownerDocument?->saveHTML( $element ) ?: '' );

		$this->isCollectable( $element, type: self::COLLECT_NODE ) && ( $collection[2] = $element );

		return $collection;
	}

	public function reset(): void {
		unset( $this->content );
	}

	/**
	 * @param self::COLLECT_* $type
	 * @phpstan-assert-if-true =DOMElement $element
	 */
	private function isCollectable( string|DOMElement $element, string $type ): bool {
		return $this->collect[ $type ] && $element instanceof DOMElement;
	}
}
