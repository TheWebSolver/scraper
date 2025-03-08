<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Traits;

use DOMElement;

trait DOMAttributeAware {
	public function domElementHasId( DOMElement $node, string $id ): bool {
		foreach ( $node->attributes as $attribute ) {
			if ( 'id' === $attribute->name && str_contains( $attribute->value, $id ) ) {
				return true;
			}
		}

		return false;
	}
}
