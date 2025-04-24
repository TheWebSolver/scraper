<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Fixture;

use DOMElement;
use TheWebSolver\Codegarage\Scraper\Decorator\HtmlEntityDecode;
use TheWebSolver\Codegarage\Scraper\Interfaces\Transformer;
use TheWebSolver\Codegarage\Scraper\Marshaller\MarshallItem;

/**
 * @template TScope of object
 * @template-implements Transformer<TScope,string>
 */
class StripTags implements Transformer {
	/** @param string|DOMElement|array{0:string,1:string,2:string,3:string,4:string} $el */
	public function transform( string|array|DOMElement $el, object $scope ): string {
		$base = new HtmlEntityDecode( new MarshallItem() );

		return strip_tags( explode( '[', $base->transform( $el, $scope ), 2 )[0] );
	}
}
