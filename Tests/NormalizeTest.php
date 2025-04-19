<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TheWebSolver\Codegarage\Scraper\Enums\Table;
use TheWebSolver\Codegarage\Scraper\Helper\Normalize;

class NormalizeTest extends TestCase {
	#[Test]
	public function itConvertsNonBreakingSpaceToSpace(): void {
		$this->assertSame( 'te st', Normalize::nonBreakingSpaceToWhitespace( 'te&nbsp;st' ) );
	}

	#[Test]
	#[DataProvider( 'provideStringWithControlAndWhitespace' )]
	public function itStripsControlAndWhitespaceCharacters( string $expected, string $value ): void {
		$this->assertSame( $expected, Normalize::controlsAndWhitespacesIn( $value ) );
	}

	/** @return string[][] */
	public static function provideStringWithControlAndWhitespace(): array {
		return [
			[ 'it passes', 'it   		  &nbsp;  passes' ],
			[
				'<b> This also <br> passes! </b>',
				'
				<b> 		        This&nbsp;
				    			   &nbsp;also    <br>&nbsp;
									        passes
																!
										  </b>
				',
			],
		];
	}

	/**
	 * @param list<mixed>      $array
	 * @param list<int>        $offset
	 * @param array<int,mixed> $expected
	 * @param list<int>        $expectedOffsets
	 */
	#[Test]
	#[DataProvider( 'provideArrayWithOffsetKeys' )]
	public function itReIndexesGivenArrayWithOffset(
		array $array,
		array $offset,
		array $expected,
		?array $expectedOffsets = null
	): void {
		[$reindexed, $offsetFlipped, $lastIndex] = Normalize::listWithOffset( $array, $offset );

		$this->assertSame( $expected, $reindexed );
		$this->assertSame( array_key_last( $expected ), $lastIndex );
		$this->assertSame( array_flip( $expectedOffsets ?? $offset ), $offsetFlipped );
	}

	/** @return mixed[] */
	// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	public static function provideArrayWithOffsetKeys(): array {
		return [
			[ [ 'without', 'offset' ], [], [ 0 => 'without', 1 => 'offset' ] ],
			[ [ '2', '5', '6' ], [ 0, 1, 3, 4 ], [ 2 => '2', 5 => '5', 6 => '6' ] ],
			[ [ '0', '1', '5' ], [ 2, 3, 4 ], [ 0 => '0', 1 => '1', 5 => '5' ] ],
			[ [ '0', '3', '4' ], [ 1, 2 ], [ 0 => '0', 3 => '3', 4 => '4' ] ],
			[ [ '3', '6' ], [ 0, 1, 2, 4, 5 ], [ 3 => '3', 6 => '6' ] ],
			[ [ '2', '4', '6' ], [ 0, 1, 3, 5 ], [ 2 => '2', 4 => '4', 6 => '6' ] ],
			[ [ '3', '4', '7' ], [ 0, 1, 2, 5, 6 ], [ 3 => '3', 4 => '4', 7 => '7' ] ],
			[ [ Table::Row, Table::Column ], [ 0, 1, 2 ], [ 3 => Table::Row, 4 => Table::Column ] ],
			[ [ 'one', 'three' ], [ 0, 2, 4, 5, 7 ], [ 1 => 'one', 3 => 'three' ], [ 0, 2 ] ],
		];
	}
}
