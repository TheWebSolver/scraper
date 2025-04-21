<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

class Normalize {
	final public const NON_BREAKING_SPACES = [ '&nbsp;', /* "&" entity is "&amp;" + "nbsp;" */ '&amp;nbsp;' ];
	final public const CONTROLS            = [ "\n", "\r", "\t", "\v" ];

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
	 * @param list<TValue> $array  Eg: **['one', 'three', 'five']**
	 * @param list<int>    $offset Eg: **[0, 2, 4]**
	 *
	 * @return array{0:array<int,TValue>,1:array<int,int>,2:int} Returns:
	 * - **0:** Resulted array -> `[1=>'one', 3=>'three', 5=>'five']`
	 * - **1:** Flipped offset -> `[0=>0, 2=>1, 4=>2]`
	 * - **2:** Last index key -> `5` of the resulted array
	 *
	 * @template TValue
	 */
	public static function listWithOffset( array $array, array $offset ): array {
		if ( empty( $offset ) ) {
			return [ $array, [], array_key_last( $array ) ?? 0 ];
		}

		$skipList    = array_flip( $offset );
		$lastSkipped = (int) array_key_last( $skipList );
		$collectList = [];
		$current     = 0;

		for ( $i = 0; $i <= $lastSkipped; $i++ ) {
			if ( ! isset( $skipList[ $i ] ) && isset( $array[ $current ] ) ) {
				$collectList[ $i ] = $array[ $current ];

				unset( $array[ $current++ ] );
			}
		}

		foreach ( $array as $value ) {
			$collectList[ ++$lastSkipped ] = $value;
		}

		$lastKey  = (int) array_key_last( $collectList );
		$skipList = array_filter( $skipList, static fn( int $skip ) => $skip < $lastKey, ARRAY_FILTER_USE_KEY );

		return [ $collectList, $skipList, $lastKey ];
	}

	/**
	 * Returns array with first index as matched or not and second index contains extracted list.
	 *
	 * @return array{0:int|false,1:list<array{0:string,1:string,2:string}>} List Contains:
	 * - **0:** Whole table row's opening tag to closing tag.
	 * - **1:** The attribute part
	 * - **2:** The content part
	 */
	public static function tableRowsFrom( string $string ): array {
		$matched = preg_match_all(
			pattern: '/<tr(.*?)>(.*?)<\/tr>/',
			subject: $string,
			matches: $tableRows,
			flags: PREG_SET_ORDER
		);

		return [ $matched, $tableRows ];
	}

	/**
	 * Returns array with first index as matched or not and second index contains extracted list.
	 *
	 * @return array{0:int|false,1:list<array{0:string,1:string,2:string,3:string,4:string}>} List contains:
	 * - **0:** Whole table head/column's opening tag to closing tag.
	 * - **1:** Opening `td` or `th`
	 * - **2:** The attribute part
	 * - **3:** The content part
	 * - **4:** Closing `td` or `th`
	 */
	public static function tableColumnsFrom( string $string ): array {
		$matched = preg_match_all(
			pattern: '/<(th|td)(.*?)>(.*?)<\/(th|td)>/',
			subject: $string,
			matches: $tableColumns,
			flags: PREG_SET_ORDER
		);

		return [ $matched, $tableColumns ];
	}
}
