<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

use Closure;
use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<string> */
class Marshaller implements Transformer {
	/** @var Closure(string|DomElement): string */
	private Closure $callback;
	/** @var array<int,string> $content */
	private array $content;
	/** @var array<self::COLLECT_*,bool> */
	private array $collect = array(
		self::COLLECT_HTML => false,
		self::COLLECT_NODE => false,
	);

	/** @return array<self::COLLECT_*,bool> */
	public function collectables(): array {
		return $this->collect;
	}

	/** @return array<int,string> */
	public function getContent(): array {
		return $this->content ?? array();
	}

	public function collectHtml(): void {
		$this->collect[ self::COLLECT_HTML ] = true;
	}

	public function collectElement(): void {
		$this->collect[ self::COLLECT_NODE ] = true;
	}

	/** @param callable(string|DomElement): string $callback */
	public function marshallWith( callable $callback ): void {
		$this->callback = $callback( ... );
	}

	public function collect( string|DOMElement $element, bool $onlyContent = false ): string|array {
		$content  = $element instanceof DOMElement ? $element->textContent : $element;
		$marshall = $this->callback ?? null;
		$content  = trim(
			Normalize::nonBreakingSpaceToWhitespace( $marshall ? $marshall( $element ) : $content )
		);

		$this->content[] = $content;

		if ( $onlyContent ) {
			return $content;
		}

		$collection = array( $content );

		$this->isCollectable( $element, type: self::COLLECT_HTML )
			&& ( $collection[1] = $element->ownerDocument?->saveHTML( $element ) ?: '' );

		$this->isCollectable( $element, type: self::COLLECT_NODE ) && ( $collection[2] = $element );

		return $collection;
	}

	public function flushContent(): void {
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
