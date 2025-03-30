<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

use TheWebSolver\Codegarage\Scraper\Error\InvalidSource;

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

	/**
	 * @param list<TValue>   $array  Eg: **['one', 'three', 'five']**
	 * @param array<int,int> $offset Eg: **[0, 2, 4]**
	 *
	 * @return array{0:array<int,TValue>,1:array<int,int>,2:int} Returns:
	 * - **0:** Resulted array -> `[1=>'one', 3=>'three', 5=>'five']`
	 * - **1:** Flipped offset -> `[0=>0, 2=>1, 4=>2]`
	 * - **2:** Last index key -> `5` of the resulted array
	 *
	 * @throws InvalidSource When last offset index not found even if non-empty $offset given.
	 *
	 * @template TValue
	 */
	public static function listWithOffset( array $array, array $offset ): array {
		if ( empty( $offset ) ) {
			return array( $array, array(), array_key_last( $array ) ?? 0 );
		}

		$lastOffset = end( $offset ) ?? throw new InvalidSource( 'Last offset index not found' );
		$offset     = array_flip( $offset );
		$result     = array();
		$current    = 0;

		for ( $i = 0; $i <= $lastOffset; $i++ ) {
			isset( $offset[ $i ] ) || ( $result[ $i ] = $array[ $current++ ] );
		}

		foreach ( $array as $value ) {
			in_array( $value, $result, strict: true ) || ( $result[ ++$lastOffset ] = $value );
		}

		return array( $result, $offset, $lastOffset );
	}
}
