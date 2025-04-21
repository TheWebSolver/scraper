<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Scraper\Helper;

use BackedEnum;

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
				// Collect every item that is not in offset list but in original list.
				$collectList[ $i ] = $array[ $current ];

				unset( $array[ $current++ ] );

			} elseif ( isset( $skipList[ $i ] ) && ! isset( $array[ $current ] ) ) {
				// Remove every offset item that is beyond last item in original list.
				// Extra offsets beyond last item in original list makes no sense.
				unset( $skipList[ $i ] );
			}
		}

		// Collect remaining items in original list beyond last offset position.
		foreach ( $array as $value ) {
			$collectList[ ++$lastSkipped ] = $value;
		}

		return [ $collectList, $skipList, (int) array_key_last( $collectList ) ];
	}

	/**
	 * Returns array with first index as matched or not and second index contains extracted list.
	 *
	 * @param string|BackedEnum<TType> $tagName
	 * @return (
	 *   $all is true
	 *     ? array{0:int|false,1:list<array{0:string,1:string,2:string}>}
	 *     : array{0:int|false,1:array{0:string,1:string,2:string}}
	 * )
	 *
	 * Array Contains:
	 * - **0:** Whole matched html node from opening tag to closing tag.
	 * - **1:** The attribute part
	 * - **2:** The content part
	 *
	 * @template TType of int|string
	 */
	public static function nodeToMatchedArray( string $node, string|BackedEnum $tagName, bool $all = false ): array {
		$tagName = $tagName instanceof BackedEnum ? $tagName->value : $tagName;
		$pattern = "/<{$tagName}(.*?)>(.*?)<\/{$tagName}>/";
		$matched = $all
			? preg_match_all( $pattern, $node, matches: $parts, flags: PREG_SET_ORDER )
			: preg_match( $pattern, $node, matches: $parts );

		return [ $matched, $parts ];
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
