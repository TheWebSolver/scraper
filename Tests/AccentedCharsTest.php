<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Scraper\Enums\AccentedChars;

class AccentedCharsTest extends TestCase {
	#[Test]
	#[DataProvider( 'provideActions' )]
	public function itGetsActions( string $action, AccentedChars $enum ): void {
		$this->assertSame( $action, $enum->action() );
	}

	/** @return mixed[] */
	public static function provideActions(): array {
		return [
			[ 'Transliterated', AccentedChars::Translit ],
			[ 'Escaped', AccentedChars::Escape ],
		];
	}

	#[Test]
	#[DataProvider( 'provideEnumTranslationName' )]
	public function itTranslatesCorrespondingEnumCaseByItsName( string $name, ?AccentedChars $expected ): void {
		$this->assertSame( $expected, AccentedChars::tryFromName( $name ) );
	}

	/** @return mixed[] */
	public static function provideEnumTranslationName(): array {
		return [
			[ 'escape', AccentedChars::Escape ],
			[ 'escaped', null ],
			[ 'translit', AccentedChars::Translit ],
			[ 'TransLit', AccentedChars::Translit ],
			[ 'TransLiteration', null ],
		];
	}
}
