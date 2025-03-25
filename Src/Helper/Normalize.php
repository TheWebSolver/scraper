<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

class Normalize {
	final public const NON_BREAKING_SPACES = array( '&nbsp;', /* "&" entity is "&amp;" + "nbsp;" */ '&amp;nbsp;' );
	final public const CONTROLS            = array( "\n", "\r", "\t", "\v" );

	public static function nonBreakingSpaceToWhitespace( string $value ): string {
		return html_entity_decode( str_replace( self::NON_BREAKING_SPACES, ' ', htmlspecialchars( $value ) ) );
	}

	public static function controlsAndWhitespacesIn( string $value ): string {
		$withoutControls = str_replace(
			search: self::CONTROLS,
			replace: '',
			subject: self::nonBreakingSpaceToWhitespace( $value )
		);

		$toSingleWhitespace = preg_replace(
			pattern: '!\s+!',
			replacement: ' ',
			subject: $withoutControls
		);

		$withoutWhitespaceBetweenTags = preg_replace(
			pattern: '/>\s+</',
			replacement: '><',
			subject: $toSingleWhitespace ?? $withoutControls
		);

		return trim( $withoutWhitespaceBetweenTags ?? $toSingleWhitespace ?? $withoutControls );
	}
}
