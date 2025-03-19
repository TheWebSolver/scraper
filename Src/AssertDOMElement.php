<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Iterator;
use DOMElement;

class AssertDOMElement {
	public static function hasId( DOMElement $element, string $id ): bool {
		return self::has( $element, $id, type: 'id' );
	}

	public static function hasClass( DOMElement $element, string $classname ): bool {
		return self::has( $element, $classname, type: 'class' );
	}

	/** @phpstan-assert-if-true =DOMElement $node */
	public static function isValid( mixed $node, string $type = '' ): bool {
		return $node instanceof DOMElement && ( ! $type || $type === $node->tagName );
	}

	public static function isNextIn( Iterator $iterator, string $type ): bool {
		while ( ! self::isValid( $iterator->current(), $type ) ) {
			if ( ! $iterator->valid() ) {
				return false;
			}

			$iterator->next();
		}

		return true;
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
