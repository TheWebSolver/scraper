<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

use DOMNode;
use DOMNodeList;

class Normalize {
	public static function nonBreakingSpaceToWhitespace( string $value ): string {
		return html_entity_decode( str_replace( '&nbsp;', ' ', htmlentities( $value ) ) );
	}

	/**
	 * @param DOMNodeList<DOMNode> $nodes
	 * @return DOMNode[]
	 */
	public static function nodesToArray( DOMNodeList $nodes ): array {
		return iterator_to_array( $nodes, preserve_keys: false );
	}
}
