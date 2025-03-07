<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

class Normalize {
	public static function nonBreakingSpaceToWhitespace( string $value ): string {
		return html_entity_decode( str_replace( '&nbsp;', ' ', htmlentities( $value ) ) );
	}
}
