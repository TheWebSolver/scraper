<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use DOMElement;

class AssertDOMElement {
	public static function hasId( DOMElement $element, string $id ): bool {
		return self::has( $element, $id, type: 'id' );
	}

	public static function hasClass( DOMElement $element, string $classname ): bool {
		return self::has( $element, $classname, type: 'class' );
	}

	private static function has( DOMElement $element, string $value, string $type ): bool {
		foreach ( $element->attributes as $attribute ) {
			if ( $type === $attribute->name && str_contains( $attribute->value, $value ) ) {
				return true;
			}
		}

		return false;
	}
}
