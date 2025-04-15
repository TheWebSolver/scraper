<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper;

use Iterator;
use BackedEnum;
use DOMElement;

class AssertDOMElement {
	public static function hasId( DOMElement $element, string $id ): bool {
		return self::has( $element, $id, type: 'id' );
	}

	public static function hasClass( DOMElement $element, string $classname ): bool {
		return self::has( $element, $classname, type: 'class' );
	}

	/**
	 * @param string|BackedEnum<T> $type
	 * @phpstan-assert-if-true =DOMElement $node
	 * @template T of string|int
	 */
	public static function isValid( mixed $node, string|BackedEnum $type = '' ): bool {
		return $node instanceof DOMElement
			&& ( ! $type || ( $type instanceof BackedEnum ? $type->value : $type ) === $node->tagName );
	}

	/**
	 * @param BackedEnum<T> $type
	 * @template T of string|int
	 */
	public static function nextIn( Iterator $iterator, string|BackedEnum $type ): ?DOMElement {
		while ( ! self::isValid( $current = $iterator->current(), $type ) ) {
			if ( ! $iterator->valid() ) {
				return null;
			}

			$iterator->next();
		}

		return $current;
	}

	/** @param BackedEnum<string> $type */
	public static function inferredFrom(
		string $string,
		string|BackedEnum $type,
		bool $normalize = true
	): ?DOMElement {
		/** @var \Iterator */
		$iterator = DOMDocumentFactory::bodyFromHtml( $string, $normalize )->childNodes->getIterator();

		return self::nextIn( $iterator, $type );
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
