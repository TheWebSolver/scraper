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
		return array(
			array( 'it passes', 'it   		  &nbsp;  passes' ),
			array(
				'<b> This also <br> passes! </b>',
				'
				<b> 		        This&nbsp;
				    			   &nbsp;also    <br>&nbsp;
									        passes
																!
										  </b>
				',
			),
		);
	}

	/**
	 * @param list<mixed>      $array
	 * @param list<int>        $offset
	 * @param array<int,mixed> $expected
	 */
	#[Test]
	#[DataProvider( 'provideArrayWithOffsetKeys' )]
	public function itReIndexesGivenArrayWithOffset( array $array, array $offset, array $expected ): void {
		[$reindexed, $offsetFlipped, $lastIndex] = Normalize::listWithOffset( $array, $offset );

		$this->assertSame( $expected, $reindexed );
		$this->assertSame( array_key_last( $expected ), $lastIndex );
		$this->assertSame( array_flip( $offset ), $offsetFlipped );
	}

	/** @return mixed[] */
	// phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	public static function provideArrayWithOffsetKeys(): array {
		return array(
			array( array( 'without', 'offset' ), array(), array( 0 => 'without', 1 => 'offset' ) ),
			array( array( '2', '5', '6' ), array( 0, 1, 3, 4 ), array( 2 => '2', 5 => '5', 6 => '6' ) ),
			array( array( '0', '1', '5' ), array( 2, 3, 4 ), array( 0 => '0', 1 => '1', 5 => '5' ) ),
			array( array( '0', '3', '4' ), array( 1, 2 ), array( 0 => '0', 3 => '3', 4 => '4' ) ),
			array( array( '3', '6' ), array( 0, 1, 2, 4, 5 ), array( 3 => '3', 6 => '6' ) ),
			array( array( '2', '4', '6' ), array( 0, 1, 3, 5 ), array( 2 => '2', 4 => '4', 6 => '6' ) ),
			array( array( '3', '4', '7' ), array( 0, 1, 2, 5, 6 ), array( 3 => '3', 4 => '4', 7 => '7' ) ),
			array( array( Table::Row, Table::Column ), array( 0, 1, 2 ), array( 3 => Table::Row, 4 => Table::Column ) ),
		);
	}
}
