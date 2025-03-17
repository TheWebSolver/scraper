<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

use DOMNode;
use DOMNodeList;

class Normalize {
	final public const NON_BREAKING_SPACES = array( '&nbsp;', /* "&" entity is "&amp;" + "nbsp;" */ '&amp;nbsp;' );

	public static function nonBreakingSpaceToWhitespace( string $value ): string {
		return html_entity_decode( str_replace( self::NON_BREAKING_SPACES, ' ', htmlspecialchars( $value ) ) );
	}

	/**
	 * @param DOMNodeList<DOMNode> $nodes
	 * @return DOMNode[]
	 */
	public static function nodesToArray( DOMNodeList $nodes ): array {
		return iterator_to_array( $nodes, preserve_keys: false );
	}

	public static function toHtmlDecodedSingleWhitespace( string $value ): string {
		return trim(
			html_entity_decode( preg_replace( '!\s+!', replacement: ' ', subject: $value ) ?? $value )
		);
	}
}
