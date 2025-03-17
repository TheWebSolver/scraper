<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
}
