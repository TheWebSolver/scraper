<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Decorator;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/**
 * @template TScope of object
 * @template-implements Transformer<TScope,string>
 */
class HtmlEntityDecode implements Transformer {
	/** @param Transformer<contravariant TScope,string> $base */
	public function __construct( private readonly Transformer $base ) {}

	public function transform( string|array|DOMElement $element, object $scope ): mixed {
		$value = $this->base->transform( $element, $scope );

		return $value ? html_entity_decode( $value ) : $value;
	}
}
