<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use DOMNode;
use DOMElement;

class AssertDOMElement {
	public static function hasId( DOMElement $element, string $id ): bool {
		return self::has( $element, $id, type: 'id' );
	}

	public static function hasClass( DOMElement $element, string $classname ): bool {
		return self::has( $element, $classname, type: 'class' );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	public static function isValid( DOMNode $node, int $childCount = 0 ): bool {
		return $node instanceof DOMElement && ( ! $childCount || $node->childNodes->length >= $childCount );
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
