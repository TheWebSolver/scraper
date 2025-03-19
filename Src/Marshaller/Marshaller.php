<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use Closure;
use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/** @template-implements Transformer<string> */
class Marshaller implements Transformer {
	/** @var Closure(string|DomElement): string */
	private Closure $callback;

	public function with( callable $callback ): static {
		$this->callback = $callback( ... );

		return $this;
	}

	public function transform( string|DOMElement $element ): string {
		$content  = $element instanceof DOMElement ? $element->textContent : $element;
		$marshall = $this->callback ?? null;

		return trim( $marshall ? $marshall( $element ) : $content );
	}
}
