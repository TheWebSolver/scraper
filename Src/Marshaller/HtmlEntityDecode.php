<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Marshaller;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;

/**
 * @template TScope of object
 * @template-implements Transformer<TScope,string>
 */
class HtmlEntityDecode implements Transformer {
	/** @param Transformer<TScope,string> $transformer */
	public function __construct( private readonly Transformer $transformer ) {}

	public function transform( string|array|DOMElement $element, object $scope ): mixed {
		return html_entity_decode( $this->transformer->transform( $element, $scope ) );
	}
}
